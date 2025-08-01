/**
 * Admin JavaScript for Klage.Click Hub
 */

jQuery(document).ready(function($) {
    
    // Tab functionality
    window.switchTab = function(evt, tabName) {
        var i, tabcontent, tablinks;
        
        // Hide all tab content
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
        }
        
        // Remove active class from all tab buttons
        tablinks = document.getElementsByClassName("nav-tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("nav-tab-active");
        }
        
        // Show the specific tab content and add active class to the button
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("nav-tab-active");
        
        evt.preventDefault();
    };
    
    // Auto-generate case ID
    $('#case_id').on('focus', function() {
        if ($(this).val() === '') {
            var timestamp = Date.now().toString().substr(-4);
            var caseId = 'SPAM-' + new Date().getFullYear() + '-' + timestamp;
            $(this).val(caseId);
        }
    });
    
    // Case ID uniqueness validation
    $('#case_id').on('blur', function() {
        var caseId = $(this).val();
        var currentCaseId = $('input[name="original_case_id"]').val(); // For edit forms
        
        if (caseId && caseId !== currentCaseId) {
            $.ajax({
                url: cah_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'check_case_id_unique',
                    case_id: caseId,
                    nonce: cah_ajax.nonce
                },
                success: function(response) {
                    var validationDiv = $('#case_id_validation');
                    if (response.success) {
                        if (response.data.unique) {
                            validationDiv.html('<span style="color: green;">✅ Fall-ID verfügbar</span>').show();
                            $('#case_id').css('border-color', '#00a32a');
                        } else {
                            validationDiv.html('<span style="color: red;">❌ Fall-ID bereits vergeben</span>').show();
                            $('#case_id').css('border-color', '#d63638');
                        }
                    }
                }
            });
        }
    });
    
    // Helper function to generate case ID
    function generateCaseId() {
        var year = new Date().getFullYear();
        var random = Math.floor(Math.random() * 9000) + 1000;
        return 'SPAM-' + year + '-' + random;
    }
    
    // Show success/error messages
    function showNotice(message, type) {
        var noticeClass = 'notice-' + type;
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
    
    // Form validation
    $('form').on('submit', function() {
        var requiredFields = $(this).find('[required]');
        var isValid = true;
        
        requiredFields.each(function() {
            if ($(this).val() === '') {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!isValid) {
            showNotice('Bitte füllen Sie alle erforderlichen Felder aus', 'error');
            return false;
        }
    });
    
    // Email validation
    $('input[type="email"]').on('blur', function() {
        var email = $(this).val();
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            $(this).addClass('error');
            showNotice('Bitte geben Sie eine gültige E-Mail-Adresse ein', 'warning');
        } else {
            $(this).removeClass('error');
        }
    });
});