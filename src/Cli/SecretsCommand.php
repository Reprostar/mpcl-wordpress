<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Cli;

use Reprostar\MpclWordpress\Security\KeyMaterial;
use Reprostar\MpclWordpress\Security\Secrets;
use Reprostar\MpclWordpress\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Manages the key that encrypts the stored API token.
 *
 * The key is MPCL_ENCRYPTION_KEY when it is defined in wp-config.php, otherwise
 * the site's auth salt, which `wp config shuffle-salts` replaces.
 */
final class SecretsCommand
{
    public const TOKEN_EMPTY = 'empty';
    public const TOKEN_PLAINTEXT = 'plaintext';
    public const TOKEN_OK = 'ok';
    public const TOKEN_SALT_FALLBACK = 'ok-salt-fallback';
    public const TOKEN_UNREADABLE = 'unreadable';

    private SettingsRepository $settings;
    private Secrets $secrets;
    private KeyMaterial $keys;
    private WpConfigFile $wpConfig;
    private Console $console;

    public function __construct(SettingsRepository $settings, Secrets $secrets, KeyMaterial $keys, WpConfigFile $wpConfig, Console $console)
    {
        $this->settings = $settings;
        $this->secrets = $secrets;
        $this->keys = $keys;
        $this->wpConfig = $wpConfig;
        $this->console = $console;
    }

    /**
     * Prints a new random key for MPCL_ENCRYPTION_KEY.
     *
     * Without --write, only the key is printed. With --write, the key is written
     * into wp-config.php and the stored API token is re-encrypted with it in the
     * same run, so the site never holds a token it cannot read.
     *
     * ## OPTIONS
     *
     * [--write]
     * : Write the key into wp-config.php and re-encrypt the stored token with it.
     *
     * ## EXAMPLES
     *
     *     wp mpcl secrets generate-key --write
     *
     * @subcommand generate-key
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function generateKey(array $args, array $assocArgs): void
    {
        $key = bin2hex(random_bytes(32));

        if (!isset($assocArgs['write'])) {
            $this->console->line($key);

            return;
        }

        if (null !== $this->keys->constant()) {
            $this->console->fail(__('MPCL_ENCRYPTION_KEY is already defined. Use "wp mpcl secrets rekey" to switch between keys.', 'mpcl'));
        }

        $token = $this->readStoredToken($this->secrets);

        try {
            $this->wpConfig->defineConstant('MPCL_ENCRYPTION_KEY', $key);
        } catch (\RuntimeException $e) {
            /* translators: %s: reason reported while changing wp-config.php. */
            $this->console->fail(sprintf(__('Could not write MPCL_ENCRYPTION_KEY into wp-config.php: %s. Nothing was changed.', 'mpcl'), $e->getMessage()));
        }

        if (null !== $token) {
            $this->storeToken($token, $this->secrets->withMaterial($key));
        }

        $this->console->success(__('MPCL_ENCRYPTION_KEY was written into wp-config.php and the API token is encrypted with it.', 'mpcl'));
    }

    /**
     * Re-encrypts the stored API token from one key to the other.
     *
     * Adding the constant: define MPCL_ENCRYPTION_KEY in wp-config.php, then run
     * `--from=salt --to=constant` (or use `generate-key --write`, which does both).
     * Until then the token still reads, encrypted with the salt.
     *
     * Removing the constant: run `--from=constant --to=salt` while the constant is
     * still defined, then remove it from wp-config.php.
     *
     * After `wp config shuffle-salts` without the constant, pipe the previous
     * auth salt (AUTH_KEY . AUTH_SALT, as wp_salt('auth') returned it) to
     * `--from-stdin --to=salt`.
     *
     * ## OPTIONS
     *
     * [--from=<source>]
     * : Key the token is encrypted with now.
     * ---
     * options:
     *   - salt
     *   - constant
     * ---
     *
     * --to=<source>
     * : Key to encrypt the token with.
     * ---
     * options:
     *   - salt
     *   - constant
     * ---
     *
     * [--from-stdin]
     * : Read the old key material from STDIN instead of --from. Never pass it as an argument.
     *
     * ## EXAMPLES
     *
     *     wp mpcl secrets rekey --from=salt --to=constant
     *     wp mpcl secrets rekey --from=constant --to=salt
     *     printf %s "$OLD_AUTH_SALT" | wp mpcl secrets rekey --from-stdin --to=salt
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function rekey(array $args, array $assocArgs): void
    {
        $from = is_string($assocArgs['from'] ?? null) ? $assocArgs['from'] : null;
        $to = is_string($assocArgs['to'] ?? null) ? $assocArgs['to'] : '';
        $fromStdin = isset($assocArgs['from-stdin']);

        if (null === $from && !$fromStdin) {
            $this->console->fail(__('Pass --from=<salt|constant> or --from-stdin.', 'mpcl'));
        }
        if (!$fromStdin && $from === $to) {
            $this->console->fail(__('--from and --to are the same key.', 'mpcl'));
        }

        $toMaterial = $this->keys->forSource($to);
        if (null === $toMaterial) {
            $this->console->fail(__('MPCL_ENCRYPTION_KEY is not defined. Add it to wp-config.php first, or use "wp mpcl secrets generate-key --write".', 'mpcl'));
        }

        $fromMaterial = $fromStdin ? $this->readMaterialFromStdin() : $this->keys->forSource((string) $from);
        if (null === $fromMaterial) {
            $this->console->fail(__('MPCL_ENCRYPTION_KEY is not defined, so the token cannot be encrypted with it.', 'mpcl'));
        }

        $token = $this->readStoredToken($this->secrets->withMaterial($fromMaterial));
        if (null === $token) {
            $this->console->success(__('No API token is stored; nothing to re-encrypt.', 'mpcl'));

            return;
        }

        $target = $this->secrets->withMaterial($toMaterial);
        $this->storeToken($token, $target);
        if ($target->decrypt($this->settings->loadStoredToken()) !== $token) {
            $this->console->fail(__('The re-encrypted token could not be read back.', 'mpcl'));
        }

        $this->console->success(__('The API token was re-encrypted.', 'mpcl'));
        if (KeyMaterial::SOURCE_SALT === $to && null !== $this->keys->constant()) {
            $this->console->log(__('You can now remove MPCL_ENCRYPTION_KEY from wp-config.php.', 'mpcl'));
        }
    }

    /**
     * Shows which key is in use and whether the stored token decrypts with it.
     * Prints no key material and no token.
     *
     * Token states: empty, plaintext (saved by 1.x, encrypted on the next load),
     * ok, ok-salt-fallback (MPCL_ENCRYPTION_KEY is defined but the token is still
     * encrypted with the salt; it reads, and `rekey --from=salt --to=constant`
     * moves it), unreadable.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function status(array $args, array $assocArgs): void
    {
        $this->console->record(is_string($assocArgs['format'] ?? null) ? $assocArgs['format'] : 'table', [
            'key_source' => $this->keys->activeSource(),
            'token' => $this->tokenState(),
        ]);
    }

    private function tokenState(): string
    {
        $stored = $this->settings->loadStoredToken();
        if ('' === $stored) {
            return self::TOKEN_EMPTY;
        }
        if (!$this->secrets->isEncrypted($stored)) {
            return self::TOKEN_PLAINTEXT;
        }
        if (null !== $this->secrets->decrypt($stored)) {
            return self::TOKEN_OK;
        }

        $fallback = $this->secrets->fallback();

        return null !== $fallback && null !== $fallback->decrypt($stored) ? self::TOKEN_SALT_FALLBACK : self::TOKEN_UNREADABLE;
    }

    /**
     * @return string|null the token in plain text, null when none is stored
     */
    private function readStoredToken(Secrets $secrets): ?string
    {
        $stored = $this->settings->loadStoredToken();
        if ('' === $stored) {
            return null;
        }
        if (!$secrets->isEncrypted($stored)) {
            return $stored;
        }

        $token = $secrets->decrypt($stored);
        if (null === $token) {
            $this->console->fail(__('The stored API token cannot be decrypted with this key. Nothing was changed.', 'mpcl'));
        }

        return $token;
    }

    private function storeToken(string $token, Secrets $secrets): void
    {
        $settings = $this->settings->load();
        $settings->apiToken = $token;
        $this->settings->save($settings, $secrets);
    }

    private function readMaterialFromStdin(): string
    {
        if ($this->console->stdinIsTerminal()) {
            $this->console->fail(__('Pipe the old key material to STDIN, e.g. printf %s "$OLD_SALT" | wp mpcl secrets rekey --from-stdin --to=salt', 'mpcl'));
        }

        $material = $this->console->readStdin();
        if ('' === $material) {
            $this->console->fail(__('STDIN is empty.', 'mpcl'));
        }

        return $material;
    }
}
