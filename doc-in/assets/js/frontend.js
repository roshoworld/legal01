/**
 * Frontend JavaScript for Document Analysis Plugin
 * (Currently minimal as this is primarily an admin plugin)
 */

jQuery(document).ready(function($) {
    
    // Any frontend communication display functionality
    $('.cah-communication').each(function() {
        var $comm = $(this);
        
        // Add click-to-expand functionality if content is long
        var $content = $comm.find('.cah-communication-content');
        if ($content.length && $content.text().length > 300) {
            var fullText = $content.text();
            var shortText = fullText.substring(0, 300) + '...';
            
            $content.text(shortText);
            
            var $expandBtn = $('<button class="cah-expand-btn">Read More</button>');
            $comm.append($expandBtn);
            
            $expandBtn.on('click', function() {
                if ($content.hasClass('expanded')) {
                    $content.text(shortText).removeClass('expanded');
                    $(this).text('Read More');
                } else {
                    $content.text(fullText).addClass('expanded');
                    $(this).text('Read Less');
                }
            });
        }
    });
    
    // Category filter functionality if implemented
    $('.cah-category-filter').on('change', function() {
        var selectedCategory = $(this).val();
        
        $('.cah-communication').each(function() {
            var $comm = $(this);
            var commCategory = $comm.find('.cah-communication-category').text();
            
            if (selectedCategory === '' || commCategory === selectedCategory) {
                $comm.show();
            } else {
                $comm.hide();
            }
        });
    });
});