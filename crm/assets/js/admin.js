/**
 * Legal Automation CRM - Admin JavaScript
 * JavaScript for CRM admin interface functionality
 */

jQuery(document).ready(function($) {
    
    // Initialize CRM functionality
    initCRM();
    
    function initCRM() {
        // Initialize forms
        initCommunicationForm();
        initEventForm();
        initAudienceManagement();
        initTemplateLoader();
        
        // Initialize UI interactions
        initModalHandlers();
        initStatusUpdaters();
        initListActions();
    }
    
    /**
     * Initialize communication form
     */
    function initCommunicationForm() {
        $('#crm-communication-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();
            
            // Show loading state
            $submitBtn.text(la_crm_admin.strings.loading).prop('disabled', true);
            
            // Prepare form data
            var formData = new FormData(this);
            
            // Send AJAX request
            $.ajax({
                url: la_crm_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        $form[0].reset();
                        refreshCommunicationsList();
                    } else {
                        showNotice('error', response.data.message || la_crm_admin.strings.error_occurred);
                    }
                },
                error: function() {
                    showNotice('error', la_crm_admin.strings.error_occurred);
                },
                complete: function() {
                    $submitBtn.text(originalText).prop('disabled', false);
                }
            });
        });
    }
    
    /**
     * Initialize event form
     */
    function initEventForm() {
        $('#crm-event-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();
            
            // Show loading state
            $submitBtn.text(la_crm_admin.strings.loading).prop('disabled', true);
            
            // Prepare form data
            var formData = new FormData(this);
            
            // Send AJAX request
            $.ajax({
                url: la_crm_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        $form[0].reset();
                        refreshEventsList();
                    } else {
                        showNotice('error', response.data.message || la_crm_admin.strings.error_occurred);
                    }
                },
                error: function() {
                    showNotice('error', la_crm_admin.strings.error_occurred);
                },
                complete: function() {
                    $submitBtn.text(originalText).prop('disabled', false);
                }
            });
        });
    }
    
    /**
     * Initialize audience management
     */
    function initAudienceManagement() {
        // Template creation buttons
        $('.create-audience-template').on('click', function() {
            var template = $(this).data('template');
            var templateName = $(this).text();
            
            var customName = prompt('Name für die Zielgruppe:', templateName);
            if (customName) {
                createAudienceFromTemplate(template, customName);
            }
        });
        
        // Audience contact count refresh
        $('.refresh-count').on('click', function() {
            var $btn = $(this);
            var audienceId = $btn.data('audience');
            var $count = $btn.siblings('.audience-count');
            
            refreshAudienceCount(audienceId, $count, $btn);
        });
        
        // View audience contacts
        $('.view-audience-contacts').on('click', function() {
            var audienceId = $(this).data('audience');
            viewAudienceContacts(audienceId);
        });
    }
    
    /**
     * Initialize template loader
     */
    function initTemplateLoader() {
        $('#load-template-btn').on('click', function() {
            var templateId = $('#template_id').val();
            if (!templateId) {
                alert('Bitte wählen Sie eine Vorlage aus.');
                return;
            }
            
            loadTemplate(templateId);
        });
    }
    
    /**
     * Initialize modal handlers
     */
    function initModalHandlers() {
        // Close modal when clicking X or outside
        $(document).on('click', '.crm-modal-close, .crm-modal', function(e) {
            if (e.target === this) {
                $('.crm-modal').hide();
            }
        });
        
        // Close modal on ESC key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) {
                $('.crm-modal').hide();
            }
        });
    }
    
    /**
     * Initialize status updaters
     */
    function initStatusUpdaters() {
        // View communication details
        $(document).on('click', '.view-communication', function() {
            var commId = $(this).data('id');
            viewCommunicationDetails(commId);
        });
        
        // View event details
        $(document).on('click', '.view-event', function() {
            var eventId = $(this).data('id');
            viewEventDetails(eventId);
        });
    }
    
    /**
     * Initialize list actions
     */
    function initListActions() {
        // Handle list refresh buttons (if added)
        $('.refresh-list').on('click', function() {
            var listType = $(this).data('list');
            if (listType === 'communications') {
                refreshCommunicationsList();
            } else if (listType === 'events') {
                refreshEventsList();
            }
        });
    }
    
    /**
     * Create audience from template
     */
    function createAudienceFromTemplate(template, customName) {
        $.ajax({
            url: la_crm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'la_crm_create_audience_from_template',
                nonce: la_crm_admin.nonce,
                template: template,
                custom_name: customName
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Zielgruppe erfolgreich erstellt');
                    location.reload(); // Refresh the page to show new audience
                } else {
                    showNotice('error', response.data.message || la_crm_admin.strings.error_occurred);
                }
            },
            error: function() {
                showNotice('error', la_crm_admin.strings.error_occurred);
            }
        });
    }
    
    /**
     * Refresh audience contact count
     */
    function refreshAudienceCount(audienceId, $countElement, $button) {
        var originalText = $button.text();
        $button.text('...');
        
        $.ajax({
            url: la_crm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'la_crm_get_audience_contacts',
                nonce: la_crm_admin.nonce,
                audience_id: audienceId
            },
            success: function(response) {
                if (response.success) {
                    $countElement.text(response.data.count);
                } else {
                    showNotice('error', 'Fehler beim Aktualisieren der Anzahl');
                }
            },
            error: function() {
                showNotice('error', la_crm_admin.strings.error_occurred);
            },
            complete: function() {
                $button.text(originalText);
            }
        });
    }
    
    /**
     * View audience contacts
     */
    function viewAudienceContacts(audienceId) {
        $.ajax({
            url: la_crm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'la_crm_get_audience_contacts',
                nonce: la_crm_admin.nonce,
                audience_id: audienceId
            },
            success: function(response) {
                if (response.success) {
                    showContactsModal(response.data.contacts);
                } else {
                    showNotice('error', response.data.message || la_crm_admin.strings.error_occurred);
                }
            },
            error: function() {
                showNotice('error', la_crm_admin.strings.error_occurred);
            }
        });
    }
    
    /**
     * Load template
     */
    function loadTemplate(templateId) {
        $.ajax({
            url: la_crm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'la_crm_load_template',
                nonce: la_crm_admin.nonce,
                template_id: templateId,
                placeholders: gatherPlaceholders()
            },
            success: function(response) {
                if (response.success) {
                    $('#subject').val(response.data.subject);
                    $('#content').val(response.data.content);
                    showNotice('success', 'Vorlage erfolgreich geladen');
                } else {
                    showNotice('error', response.data.message || la_crm_admin.strings.error_occurred);
                }
            },
            error: function() {
                showNotice('error', la_crm_admin.strings.error_occurred);
            }
        });
    }
    
    /**
     * Gather placeholder values for template
     */
    function gatherPlaceholders() {
        // This would gather values from the current context
        // For now, return basic placeholders
        return {
            law_firm_name: 'Musterkanzlei',
            date: new Date().toLocaleDateString('de-DE')
        };
    }
    
    /**
     * View communication details
     */
    function viewCommunicationDetails(commId) {
        // This would open a modal with communication details
        // For now, just show an alert
        alert('Communication ID: ' + commId + ' - Detailansicht wird noch implementiert');
    }
    
    /**
     * View event details
     */
    function viewEventDetails(eventId) {
        // This would open a modal with event details
        // For now, just show an alert
        alert('Event ID: ' + eventId + ' - Detailansicht wird noch implementiert');
    }
    
    /**
     * Show contacts modal
     */
    function showContactsModal(contacts) {
        var modalContent = '<div class="crm-modal" id="contacts-modal">' +
            '<div class="crm-modal-content">' +
            '<span class="crm-modal-close">&times;</span>' +
            '<h3>Kontakte in Zielgruppe (' + contacts.length + ')</h3>' +
            '<table class="wp-list-table widefat fixed striped">' +
            '<thead>' +
            '<tr>' +
            '<th>Name</th>' +
            '<th>E-Mail</th>' +
            '<th>Unternehmen</th>' +
            '<th>Typ</th>' +
            '</tr>' +
            '</thead>' +
            '<tbody>';
        
        contacts.forEach(function(contact) {
            modalContent += '<tr>' +
                '<td>' + escapeHtml(contact.first_name + ' ' + contact.last_name) + '</td>' +
                '<td>' + escapeHtml(contact.email || '-') + '</td>' +
                '<td>' + escapeHtml(contact.company_name || '-') + '</td>' +
                '<td>' + escapeHtml(contact.contact_type || 'client') + '</td>' +
                '</tr>';
        });
        
        modalContent += '</tbody></table></div></div>';
        
        $('body').append(modalContent);
        $('#contacts-modal').show();
    }
    
    /**
     * Refresh communications list
     */
    function refreshCommunicationsList() {
        // This would refresh the communications list via AJAX
        // For now, just reload the page
        location.reload();
    }
    
    /**
     * Refresh events list
     */
    function refreshEventsList() {
        // This would refresh the events list via AJAX
        // For now, just reload the page
        location.reload();
    }
    
    /**
     * Show notice message
     */
    function showNotice(type, message) {
        var $notice = $('<div class="crm-notice notice-' + type + '">' + escapeHtml(message) + '</div>');
        
        // Find the best place to insert the notice
        var $target = $('.case-tab-content.active .postbox').first();
        if ($target.length) {
            $target.prepend($notice);
        } else {
            $('body').prepend($notice);
        }
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 5000);
        
        // Scroll to notice
        $('html, body').animate({
            scrollTop: $notice.offset().top - 50
        }, 300);
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Tab switching functionality (integrated with core plugin)
     */
    window.switchCaseTab = function(evt, tabName) {
        // Hide all tab contents
        var tabContents = document.getElementsByClassName("case-tab-content");
        for (var i = 0; i < tabContents.length; i++) {
            tabContents[i].classList.remove("active");
        }
        
        // Remove active class from all tabs
        var tabs = document.getElementsByClassName("nav-tab");
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove("nav-tab-active");
        }
        
        // Show selected tab and mark as active
        var selectedTab = document.getElementById(tabName);
        if (selectedTab) {
            selectedTab.classList.add("active");
        }
        
        if (evt && evt.currentTarget) {
            evt.currentTarget.classList.add("nav-tab-active");
        }
        
        // Initialize CRM functionality for newly shown tab
        if (tabName === 'communications' || tabName === 'events' || tabName === 'audience') {
            initCRM();
        }
    };
});