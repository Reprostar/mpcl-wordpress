<?php
/**
 * Created by PhpStorm.
 * User: pfcode
 * Date: 16.10.16
 * Time: 17:00
 */

namespace Reprostar\MpclWordpress;


use Reprostar\MpclConnector\MpclPhotoRemoteModel;

class Utils
{

    /**
     * Parse Photo model/array and return image URI
     * @param MpclPhotoRemoteModel|array $photo
     * @param int $size
     * @return string
     */
    public static function getPhotoURI($photo, $size = -1)
    {
        $fallback = MpclPlugin::getBaseUrl() . "/img/sample_machine.png";

        if (is_object($photo) && $photo instanceof MpclPhotoRemoteModel) {
            /**
             * @var $photo MpclPhotoRemoteModel
             */
            if ($size > 0) {
                if (is_array($photo->thumbnails) && isset($photo->thumbnails[$size])) {
                    return $photo->thumbnails[$size];
                } else {
                    return $fallback;
                }
            } else {
                return $photo->orig_uri;
            }
        } else {
            if (is_array($photo)) {
                if ($size > 0) {
                    if (isset($photo['thumbnails']) && is_array($photo['thumbnails']) && isset($photo['thumbnails'][$size])) {
                        return $photo['thumbnails'][$size];
                    } else {
                        return $fallback;
                    }
                } else {
                    if (isset($photo['orig_uri'])) {
                        return $photo['orig_uri'];
                    } else {
                        return $fallback;
                    }
                }
            } else {
                return $fallback;
            }
        }
    }

    /**
     * @param $state
     * @return string|void
     */
    public static function getStateString($state)
    {
        switch ($state) {
            default:
                return __("Unknown", "mpcl");
                break;
            case '1':
                return "1/5 - " . __("Broken", "mpcl");
                break;
            case '2':
                return "2/5 - " . __("Needs repair", "mpcl");
                break;
            case '3':
                return "3/5 - " . __("Partially broken", "mpcl");
                break;
            case '4':
                return "4/5 - " . __("Good", "mpcl");
                break;
            case '5':
                return "5/5 - " . __("Very good", "mpcl");
                break;
        }
    }
}