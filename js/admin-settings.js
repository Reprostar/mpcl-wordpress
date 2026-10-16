document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('mpcl-field-connection-test');
    var result = document.getElementById('mpcl-test-connection-result');
    var config = window.mpclConnectionTest;

    if (!button || !result || !config) {
        return;
    }

    button.addEventListener('click', function () {
        var key = document.getElementById('mpcl-field-api-key');
        var token = document.getElementById('mpcl-field-api-token');
        var body = new FormData();

        body.append('action', config.action);
        body.append('nonce', config.nonce);
        body.append('api_key', key ? key.value : '');
        body.append('api_token', token ? token.value : '');

        button.disabled = true;
        result.style.color = '';
        result.textContent = config.testing;

        fetch(config.url, {method: 'POST', credentials: 'same-origin', body: body})
            .then(function (response) {
                return response.json();
            })
            .then(function (json) {
                result.style.color = json.success ? '#00a32a' : '#b32d2e';
                result.textContent = json.data && json.data.message ? json.data.message : '';
            })
            .catch(function (error) {
                result.style.color = '#b32d2e';
                result.textContent = String(error);
            })
            .then(function () {
                button.disabled = false;
            });
    });
});
