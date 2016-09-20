document.addEventListener("DOMContentLoaded", function() {
    if(typeof baguetteBox !== 'undefined'){
        baguetteBox.run('.mpcl-baguette-list', {
            async: true,
            preload: 2
        });
    }
});