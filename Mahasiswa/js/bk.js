function validateFile(input) {
    const file = input.files[0];
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    const allowedTypes = ["application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document"];
    
    if (file) {
        if (file.size > maxSize) {
            Swal.fire({
                icon: "error",
                title: "File terlalu besar",
                text: "Ukuran file maksimal adalah 5MB",
                confirmButtonText: "OK"
            });
            input.value = "";
            return false;
        }
        
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                icon: "error",
                title: "Format file tidak sesuai",
                text: "Silakan upload file dengan format PDF, DOC, atau DOCX",
                confirmButtonText: "OK"
            });
            input.value = "";
            return false;
        }
    }
    return true;
}

document.getElementById("bebasKompenForm").addEventListener("submit", async function(e) {
    e.preventDefault();
    
    // Validate file first
    if (!validateFile(document.getElementById("surat"))) {
        return;
    }

    // Show confirmation dialog before proceeding
    const selectedFile = document.getElementById("surat").files[0];
    const confirmResult = await Swal.fire({
        title: 'Konfirmasi Pengajuan',
        html: `
            <div class="text-left">
                <p>Pastikan data yang Anda pilih sudah benar:</p>
                <ul class="list-unstyled">
                    <li>✓ File yang dipilih: <strong>${selectedFile.name}</strong></li>
                    <li>✓ Ukuran file: <strong>${(selectedFile.size / 1024 / 1024).toFixed(2)} MB</strong></li>
                </ul>
                <p class="mt-3 text-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Data yang sudah disubmit tidak dapat diubah!
                </p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33'
    });

    if (!confirmResult.isConfirmed) {
        return;
    }

    const submitBtn = document.getElementById("submitBtn");
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Memproses...`;

    try {
        const response = await fetch(this.action, {
            method: "POST",
            body: new FormData(this)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Store in both session storage and let PHP handle session
            sessionStorage.setItem('uploadSuccess', 'true');
            sessionStorage.setItem('uploadedFile', data.file_name);
            
            // Reload page to show success state
            window.location.reload();
        } else {
            throw new Error(data.message || "Terjadi kesalahan saat memproses pengajuan");
        }
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "Gagal",
            text: error.message
        });
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

// Fungsi untuk menampilkan pesan sukses
function showSuccessMessage(fileName) {
    const uploadSection = document.querySelector('.upload-section');
    if (uploadSection) {
        uploadSection.style.display = 'none';
    }

    const successContainer = document.createElement('div');
    successContainer.className = 'card border-0 shadow-sm rounded-3';
    successContainer.innerHTML = `
        <div class="card-body p-4 text-center">
            <div class="success-icon mb-3">
                <i class="fas fa-check-circle text-success fa-3x"></i>
            </div>
            <h4 class="mb-3">Dokumen Berhasil Diupload!</h4>
            <p class="text-muted mb-4">File yang diupload: ${fileName}</p>
            <button onclick="clearUploadStatus()" class="btn btn-primary">
                <i class="fas fa-upload me-2"></i>Upload File Baru
            </button>
        </div>
    `;

    if (uploadSection) {
        uploadSection.parentNode.replaceChild(successContainer, uploadSection);
    }
}

// Fungsi untuk membersihkan status upload
function clearUploadStatus() {
    // Clear session storage
    sessionStorage.removeItem('uploadSuccess');
    sessionStorage.removeItem('uploadedFile');
    
    // Make AJAX call to clear PHP session
    fetch('clear_upload_status.php')
        .then(() => window.location.reload());
}


// Cek status upload saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    if (sessionStorage.getItem('uploadSuccess') === 'true') {
        const fileName = sessionStorage.getItem('uploadedFile');
        showSuccessMessage(fileName);
    }
});