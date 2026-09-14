jQuery(document).ready(function($) {
    let mediaUploader;
    let isEditing = false;
    
    // Image upload handler
    $('#upload-image').click(function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Destination Image',
            button: {
                text: 'Choose Image'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#image').val(attachment.url);
            $('#image-preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto; margin-top: 10px; border: 1px solid #ddd; border-radius: 4px; padding: 5px;">');
        });
        
        mediaUploader.open();
    });
    
    // Form submission handler
    $('#destination-form').submit(function(e) {
        e.preventDefault();
        
        // Basic validation
        if (!$('#category').val()) {
            alert('Please select a category.');
            return;
        }
        
        if (!$('#name').val().trim()) {
            alert('Please enter a destination name.');
            return;
        }
        
        if (!$('#slurl').val().trim()) {
            alert('Please enter a SLURL.');
            return;
        }
        
        // Show loading state
        const submitBtn = $('#submit');
        const originalText = submitBtn.val();
        submitBtn.val('Saving...').prop('disabled', true);
        
        const formData = {
            action: 'save_destination',
            nonce: ajax_object.nonce,
            destination_id: $('#destination-id').val(),
            category: $('#category').val(),
            name: $('#name').val().trim(),
            image: $('#image').val(),
            slurl: $('#slurl').val().trim(),
            description: $('#description').val().trim()
        };
        
        $.post(ajax_object.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    alert('Destination saved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error occurred'));
                }
            })
            .fail(function(xhr, status, error) {
                alert('Error: Failed to save destination. Please try again.');
                console.error('AJAX Error:', status, error);
            })
            .always(function() {
                submitBtn.val(originalText).prop('disabled', false);
            });
    });
    
    // Edit destination handler
    $(document).on('click', '.edit-destination', function(e) {
        e.preventDefault();
        
        const id = $(this).data('id');
        const button = $(this);
        
        // Show loading state
        button.text('Loading...').prop('disabled', true);
        
        $.post(ajax_object.ajax_url, {
            action: 'get_destination',
            nonce: ajax_object.nonce,
            id: id
        })
        .done(function(response) {
            if (response.success) {
                const dest = response.data;
                
                // Populate form fields
                $('#destination-id').val(dest.id);
                $('#category').val(dest.category);
                $('#name').val(dest.name);
                $('#image').val(dest.image);
                $('#slurl').val(dest.slurl);
                $('#description').val(dest.description);
                
                // Show image preview if exists
                if (dest.image) {
                    $('#image-preview').html('<img src="' + dest.image + '" style="max-width: 200px; height: auto; margin-top: 10px; border: 1px solid #ddd; border-radius: 4px; padding: 5px;">');
                } else {
                    $('#image-preview').empty();
                }
                
                // Update form UI
                $('#form-title').text('Edit Destination');
                $('#submit').val('Update Destination');
                $('#cancel-edit').show();
                isEditing = true;
                
                // Scroll to form smoothly
                $('html, body').animate({
                    scrollTop: $('#destination-form').offset().top - 100
                }, 500);
                
                // Focus on name field
                $('#name').focus();
            } else {
                alert('Error: ' + (response.data || 'Could not load destination'));
            }
        })
        .fail(function(xhr, status, error) {
            alert('Error: Failed to load destination data. Please try again.');
            console.error('AJAX Error:', status, error);
        })
        .always(function() {
            button.text('Edit').prop('disabled', false);
        });
    });
    
    // Cancel edit handler
    $('#cancel-edit').click(function(e) {
        e.preventDefault();
        resetForm();
    });
    
    // Delete destination handler
    $(document).on('click', '.delete-destination', function(e) {
        e.preventDefault();
        
        const id = $(this).data('id');
        const button = $(this);
        const destinationName = button.closest('.destination-item').find('h4').text();
        
        if (!confirm('Are you sure you want to delete "' + destinationName + '"?\n\nThis action cannot be undone.')) {
            return;
        }
        
        // Show loading state
        button.text('Deleting...').prop('disabled', true);
        
        $.post(ajax_object.ajax_url, {
            action: 'delete_destination',
            nonce: ajax_object.nonce,
            id: id
        })
        .done(function(response) {
            if (response.success) {
                alert('Destination deleted successfully!');
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Could not delete destination'));
                button.text('Delete').prop('disabled', false);
            }
        })
        .fail(function(xhr, status, error) {
            alert('Error: Failed to delete destination. Please try again.');
            console.error('AJAX Error:', status, error);
            button.text('Delete').prop('disabled', false);
        });
    });
    
    // Reset form function
    function resetForm() {
        $('#destination-form')[0].reset();
        $('#destination-id').val('');
        $('#image-preview').empty();
        $('#form-title').text('Add New Destination');
        $('#submit').val('Save Destination');
        $('#cancel-edit').hide();
        isEditing = false;
        
        // Scroll to top of form
        $('html, body').animate({
            scrollTop: $('#destination-form').offset().top - 100
        }, 300);
    }
    
    // Prevent form submission when pressing Enter in text fields (except textarea)
    $('#destination-form input[type="text"]').keypress(function(e) {
        if (e.which === 13) {
            e.preventDefault();
        }
    });
    
    // Auto-resize textarea
    $('#description').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Validate SLURL format on blur
    $('#slurl').blur(function() {
        const slurl = $(this).val().trim();
        if (slurl && !slurl.match(/^(secondlife|hop):\/\/.*/i)) {
            if (confirm('The SLURL should start with "secondlife://" or "hop://". Would you like to add "secondlife://" prefix?')) {
                $(this).val('secondlife://' + slurl);
            }
        }
    });
    
    // Show confirmation before leaving page if form has unsaved changes
    let formChanged = false;
    $('#destination-form input, #destination-form select, #destination-form textarea').change(function() {
        formChanged = true;
    });
    
    $(window).on('beforeunload', function() {
        if (formChanged && !isEditing) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    $('#destination-form').submit(function() {
        formChanged = false;
    });
    
    // ---- Category management (Categories admin screen) ----
    
    function updateCategoryCountUI(total) {
        const max = ajax_object.max_categories;
        $('#dg-category-count').text(total);
        
        const atLimit = total >= max;
        $('#new-category-name, #add-category-btn').prop('disabled', atLimit);
        $('#category-limit-msg').toggle(atLimit);
    }
    
    // Add category
    $('#category-add-form').submit(function(e) {
        e.preventDefault();
        
        const nameField = $('#new-category-name');
        const name = nameField.val().trim();
        
        if (!name) {
            alert('Please enter a category name.');
            return;
        }
        
        const btn = $('#add-category-btn');
        btn.prop('disabled', true).text('Adding...');
        
        $.post(ajax_object.ajax_url, {
            action: 'add_category',
            nonce: ajax_object.nonce,
            name: name
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Could not add category'));
                btn.prop('disabled', false).text('Add Category');
            }
        })
        .fail(function() {
            alert('Error: Failed to add category. Please try again.');
            btn.prop('disabled', false).text('Add Category');
        });
    });
    
    // Rename category - enter edit mode
    $(document).on('click', '.edit-category', function() {
        const row = $(this).closest('tr');
        row.find('.category-name-display').hide();
        row.find('.category-name-edit').show().focus();
        row.find('.edit-category, .delete-category').hide();
        row.find('.save-category, .cancel-category').show();
    });
    
    // Rename category - cancel
    $(document).on('click', '.cancel-category', function() {
        const row = $(this).closest('tr');
        const original = row.find('.category-name-display').text();
        row.find('.category-name-edit').val(original).hide();
        row.find('.category-name-display').show();
        row.find('.save-category, .cancel-category').hide();
        row.find('.edit-category, .delete-category').show();
    });
    
    // Rename category - save
    $(document).on('click', '.save-category', function() {
        const row = $(this).closest('tr');
        const id = row.data('category-id');
        const newName = row.find('.category-name-edit').val().trim();
        const btn = $(this);
        
        if (!newName) {
            alert('Category name cannot be empty.');
            return;
        }
        
        btn.prop('disabled', true).text('Saving...');
        
        $.post(ajax_object.ajax_url, {
            action: 'rename_category',
            nonce: ajax_object.nonce,
            id: id,
            name: newName
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Could not rename category'));
                btn.prop('disabled', false).text('Save');
            }
        })
        .fail(function() {
            alert('Error: Failed to rename category. Please try again.');
            btn.prop('disabled', false).text('Save');
        });
    });
    
    // Delete category
    $(document).on('click', '.delete-category', function() {
        const row = $(this).closest('tr');
        const id = row.data('category-id');
        const name = row.find('.category-name-display').text();
        const btn = $(this);
        
        if (!confirm('Delete the category "' + name + '"?\n\nThis only works if no destinations are using it.')) {
            return;
        }
        
        btn.prop('disabled', true).text('Deleting...');
        
        $.post(ajax_object.ajax_url, {
            action: 'delete_category',
            nonce: ajax_object.nonce,
            id: id
        })
        .done(function(response) {
            if (response.success) {
                row.remove();
                updateCategoryCountUI(response.data.total);
            } else {
                alert('Error: ' + (response.data || 'Could not delete category'));
                btn.prop('disabled', false).text('Delete');
            }
        })
        .fail(function() {
            alert('Error: Failed to delete category. Please try again.');
            btn.prop('disabled', false).text('Delete');
        });
    });
    
    // Reorder category
    $(document).on('click', '.move-category-up, .move-category-down', function() {
        const row = $(this).closest('tr');
        const id = row.data('category-id');
        const direction = $(this).hasClass('move-category-up') ? 'up' : 'down';
        
        $.post(ajax_object.ajax_url, {
            action: 'reorder_category',
            nonce: ajax_object.nonce,
            id: id,
            direction: direction
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Could not reorder category'));
            }
        })
        .fail(function() {
            alert('Error: Failed to reorder category. Please try again.');
        });
    });
});