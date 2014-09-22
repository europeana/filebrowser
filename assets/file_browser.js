$(document).ready(function(){
    var navigate = function(targetPath, container) {
        var rootPath = container.data('fb-root');
        container.append(
            $('<p class="file-browser file-browser-loading" />')
                .text('Loading...'));
        $.get('/async/file_browser?fb_cp=' + targetPath,
            function(html) {
                container.html(html);
            });
    };
    $('.file-browser-container').on('click', '.file-browser-dir a, a.file-browser-up', function(e){
        var container = $(this).closest('.file-browser-container');
        var targetPath = $(this).data('fb-cp');
        navigate(targetPath, container);
        e.preventDefault();
        e.stopPropagation();
    });
});
