let validationStates = {};

document.addEventListener('DOMContentLoaded', function() {
    initializeValidationStates();
    initializeUploadAreas();
    initializeFormSubmissions();
    loadExistingImages();
});

function initializeUploadAreas() {
    document.querySelectorAll('.evidence-form').forEach(form => {
        const workId = form.dataset.id;
        initializeDragAndDrop(form);
        initializeFileInputs(form);
    });
}

function initializeValidationStates() {
    document.querySelectorAll('.evidence-form').forEach(form => {
        const workId = form.dataset.id;
        const beforeImg = form.querySelector('img[alt="Before"]');
        const afterImg = form.querySelector('img[alt="After"]');
        
        validationStates[workId] = {
            before: beforeImg && beforeImg.style.display !== 'none' && beforeImg.src && !beforeImg.src.endsWith('undefined'),
            after: afterImg && afterImg.style.display !== 'none' && afterImg.src && !afterImg.src.endsWith('undefined')
        };
    });
}

// Add function to load existing images
function loadExistingImages() {
    document.querySelectorAll('.upload-area').forEach(area => {
        const existingImg = area.querySelector('.preview-image');
        if (existingImg && existingImg.getAttribute('src')) {
            const placeholder = area.querySelector('.upload-placeholder');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            existingImg.style.display = 'block';
        }
    });
}

function initializeDragAndDrop(form) {
    const uploadAreas = form.querySelectorAll('.upload-area');
    uploadAreas.forEach(area => {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            area.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            area.addEventListener(eventName, () => highlight(area), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            area.addEventListener(eventName, () => unhighlight(area), false);
        });

        area.addEventListener('drop', handleDrop, false);
    });
}

function initializeFileInputs(form) {
    form.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', handleFileSelect);
    });
}

function handleFileSelect(e) {
    const file = e.target.files[0];
    if (!file) return;

    if (!validateFile(file)) {
        e.target.value = '';
        return;
    }

    const uploadArea = e.target.closest('.upload-area');
    const form = e.target.closest('.evidence-form');
    const workId = form.dataset.id;
    const type = e.target.dataset.type || (uploadArea.id.includes('before') ? 'before' : 'after');
    const placeholder = uploadArea.querySelector('.upload-placeholder');
    let preview = uploadArea.querySelector('.preview-image');

    if (!preview) {
        preview = document.createElement('img');
        preview.className = 'preview-image img-fluid';
        uploadArea.appendChild(preview);
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
        if (placeholder) {
            placeholder.style.display = 'none';
        }
        
        // Update validation state
        validationStates[workId] = validationStates[workId] || {};
        validationStates[workId][type] = true;
    }
    reader.readAsDataURL(file);
}

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    const input = this.querySelector('.file-input');

    if (files.length) {
        input.files = files;
        handleFileSelect({ target: input });
    }
}

function validateFile(file) {
    // Check file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        showAlert('Error', 'File harus berupa gambar (JPG, PNG, atau GIF)', 'error');
        return false;
    }

    // Check file size (5MB max)
    const maxSize = 5 * 1024 * 1024;
    if (file.size > maxSize) {
        showAlert('Error', 'Ukuran file maksimal 5MB', 'error');
        return false;
    }

    return true;
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(element) {
    element.classList.add('dragover');
}

function unhighlight(element) {
    element.classList.remove('dragover');
}

function initializeFormSubmissions() {
    document.querySelectorAll('.evidence-form').forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });
}

async function handleFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    
    if (!validateForm(form)) {
        return;
    }

    const workId = form.dataset.id;
    const submitBtn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    
    formData.append('work_id', workId);

    // Log form data for debugging (excluding file contents)
    const formDataLog = {};
    for (let [key, value] of formData.entries()) {
        if (value instanceof File) {
            formDataLog[key] = {
                name: value.name,
                type: value.type,
                size: value.size
            };
        } else {
            formDataLog[key] = value;
        }
    }
    console.log('Submitting form data:', formDataLog);

    try {
        setLoadingState(submitBtn, true);
        
        const response = await fetch('process_evidence.php', {
            method: 'POST',
            body: formData
        });

        const contentType = response.headers.get('content-type');
        let result;

        if (contentType && contentType.includes('application/json')) {
            result = await response.json();
        } else {
            // If response is not JSON, get text content for error details
            const textContent = await response.text();
            console.error('Unexpected response:', textContent);
            throw new Error('Server returned an invalid response format');
        }

        if (!response.ok) {
            console.error('Server response:', result);
            throw new Error(result.message || `Server error: ${response.status}`);
        }

        if (result.success) {
            console.log('Upload successful:', result);
            
            Swal.fire({
                title: 'Berhasil!',
                text: 'Data berhasil disimpan',
                icon: 'success',
                showConfirmButton: false,
                timer: 1500
            });

            // Add delay before refreshing images
            await new Promise(resolve => setTimeout(resolve, 1000));

            // Refresh images with logging
            if (result.data.before_exists) {
                console.log('Refreshing before image:', workId);
                await refreshImage(form, 'before', workId);
            }
            if (result.data.after_exists) {
                console.log('Refreshing after image:', workId);
                await refreshImage(form, 'after', workId);
            }
        } else {
            throw new Error(result.message || 'Server reported failure');
        }
    } catch (error) {
        console.error('Form submission error:', error);
        
        let errorMessage = error.message;
        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            errorMessage = 'Koneksi ke server gagal. Mohon periksa koneksi internet Anda.';
        }

        Swal.fire({
            title: 'Error!',
            text: errorMessage,
            icon: 'error',
            confirmButtonColor: '#0d6efd'
        });
    } finally {
        setLoadingState(submitBtn, false);
    }
}


function setLoadingState(button, isLoading) {
    const normalText = button.querySelector('.normal-text');
    const loadingText = button.querySelector('.loading-text');

    if (isLoading) {
        button.disabled = true;
        normalText.classList.add('d-none');
        loadingText.classList.remove('d-none');
    } else {
        button.disabled = false;
        normalText.classList.remove('d-none');
        loadingText.classList.add('d-none');
    }
}

async function refreshImage(form, type, workId) {
    const uploadArea = form.querySelector(`.upload-area[id$="${type}Area-${workId}"]`);
    const placeholder = uploadArea.querySelector('.upload-placeholder');
    let preview = uploadArea.querySelector('.preview-image');

    if (!preview) {
        preview = document.createElement('img');
        preview.className = 'preview-image img-fluid';
        preview.alt = `${type} preview`;
        preview.style.cssText = 'max-height: 200px; width: auto; object-fit: contain;';
        uploadArea.appendChild(preview);
    }

    try {
        const timestamp = new Date().getTime();
        const imageUrl = `get_image.php?work_id=${workId}&type=${type}&t=${timestamp}`;
        
        // Test image loading
        const response = await fetch(imageUrl);
        if (!response.ok) {
            throw new Error(`Failed to load image: ${response.status}`);
        }

        // If image loads successfully, update the preview
        preview.src = imageUrl;
        preview.style.display = 'block';
        if (placeholder) {
            placeholder.style.display = 'none';
        }

        // Add error handler
        preview.onerror = function(e) {
            console.error(`Error loading ${type} image:`, e);
            handleImageError(preview);
        };
    } catch (error) {
        console.error(`Error refreshing ${type} image:`, error);
        handleImageError(preview);
    }
}

function handleImageError(img) {
    console.warn('Image load failed:', img.src);
    const uploadArea = img.closest('.upload-area');
    const form = img.closest('.evidence-form');
    const workId = form.dataset.id;
    const type = uploadArea.id.includes('before') ? 'before' : 'after';
    const placeholder = uploadArea.querySelector('.upload-placeholder');
    
    img.style.display = 'none';
    if (placeholder) {
        placeholder.style.display = 'block';
    }
    
    // Update validation state
    validationStates[workId] = validationStates[workId] || {};
    validationStates[workId][type] = false;

    Swal.fire({
        title: 'Peringatan',
        text: 'Gambar tidak dapat dimuat. Silakan coba upload ulang.',
        icon: 'warning',
        confirmButtonColor: '#0d6efd'
    });
}

// Modify validateForm to include image validation
function validateForm(form) {
    const workId = form.dataset.id;
    const validationState = validationStates[workId] || { before: false, after: false };

    // Check both before and after images
    if (!validationState.before || !validationState.after) {
        showAlert(
            'Error', 
            'Mohon upload foto sebelum dan sesudah pengerjaan terlebih dahulu!',
            'error'
        );
        return false;
    }

    // Continue with existing validation
    const formData = new FormData(form);
    const beforeFile = formData.get('before');
    const afterFile = formData.get('after');

    if (beforeFile instanceof File && beforeFile.size === 0) {
        showAlert('Error', 'File foto sebelum pengerjaan tidak valid', 'error');
        return false;
    }

    if (afterFile instanceof File && afterFile.size === 0) {
        showAlert('Error', 'File foto setelah pengerjaan tidak valid', 'error');
        return false;
    }

    return true;
}

function showAlert(title, message, type = 'info') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: type,
            confirmButtonColor: '#0d6efd',
            position: 'center', // Changed to center
            showConfirmButton: true,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        alert(`${title}: ${message}`);
    }
}