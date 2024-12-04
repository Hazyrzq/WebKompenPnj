function confirmDelete(kodeKegiatan) {
    Swal.fire({
        title: "Hapus Pengajuan?",
        text: "Pengajuan yang sudah dihapus tidak dapat dikembalikan",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc2626",
        cancelButtonColor: "#6b7280",
        confirmButtonText: "Ya, Hapus",
        cancelButtonText: "Batal",
        customClass: {
            popup: "rounded-lg",
            confirmButton: "rounded-md",
            cancelButton: "rounded-md"
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("delete_pengajuan.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: "kode_kegiatan=" + encodeURIComponent(kodeKegiatan)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = document.querySelector(`tr[data-code="${kodeKegiatan}"]`);
                    if (row) {
                        row.style.transition = "opacity 0.5s";
                        row.style.opacity = "0";
                        setTimeout(() => {
                            row.remove();
                            
                            // Check if table is now empty
                            const tbody = document.querySelector("tbody");
                            if (tbody.children.length === 0) {
                                tbody.innerHTML = `
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state text-center py-5">
                                                <div class="empty-icon mb-3">
                                                    <i class="fas fa-clipboard-list fa-3x text-muted"></i>
                                                </div>
                                                <h4 class="fw-normal text-muted mb-3">Belum Ada Pengajuan</h4>
                                                <p class="text-muted mb-4">Mulai dengan membuat pengajuan baru</p>
                                                <a href="?page=new-pengajuan" class="btn btn-primary">
                                                    <i class="fas fa-plus me-2"></i>Buat Pengajuan
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            }
                        }, 500);
                    }
                    
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil!",
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal!",
                        text: data.message
                    });
                }
            });
        }
    });
}

// Function untuk update UI setelah submit pengajuan berhasil
function updatePengajuanList(pengajuanData) {
    const tbody = document.querySelector("tbody");
    const newRow = document.createElement("tr");
    newRow.setAttribute("data-code", pengajuanData.kode_kegiatan);
    
    // Format date for display
    const date = new Date(pengajuanData.tanggal_pengajuan);
    const day = date.getDate().toString().padStart(2, '0');
    const month = date.toLocaleString('default', { month: 'short' });
    
    newRow.innerHTML = `
        <td>
            <div class="d-flex align-items-center">
                <div class="submission-date">
                    <div class="date">${day}</div>
                    <div class="month">${month}</div>
                </div>
            </div>
        </td>
        <td><span class="submission-code">${pengajuanData.kode_kegiatan}</span></td>
        <td>
            <div class="text-wrap work-list">${pengajuanData.pekerjaan_list}</div>
        </td>
        <td><span class="hours-badge">${formatTimeWithBoth(pengajuanData.total_jam * 60)}</span></td>
        <td>${getStatusBadge('Belum Melakukan Pekerjaan', {
            STATUS: 'Belum Melakukan Pekerjaan',
            STATUS_APPROVAL1: 'Pending',
            STATUS_APPROVAL2: 'Pending',
            STATUS_APPROVAL3: 'Pending'
        })}</td>
        <td>${getApprovalProgress({
            STATUS_APPROVAL1: 'Pending',
            STATUS_APPROVAL2: 'Pending',
            STATUS_APPROVAL3: 'Pending'
        })}</td>
        <td class="text-end">
            <div class="actions">
                <a href="?page=pengajuan-detail&kode_kegiatan=${pengajuanData.kode_kegiatan}" 
                   class="btn btn-primary" title="Lihat Detail">
                    Lanjutkan
                </a>
                <button class="btn btn-sm btn-danger ms-1" 
                        title="Hapus" 
                        onclick="confirmDelete('${pengajuanData.kode_kegiatan}')">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    `;
    
    // Add animation classes
    newRow.style.opacity = "0";
    newRow.style.transform = "translateY(-20px)";
    newRow.style.transition = "all 0.5s ease";
    
    // Remove empty state if exists
    const emptyState = tbody.querySelector(".empty-state");
    if (emptyState) {
        const emptyStateRow = emptyState.closest("tr");
        if (emptyStateRow) {
            emptyStateRow.remove();
        }
    }
    
    // Add new row at the beginning of the table
    tbody.insertBefore(newRow, tbody.firstChild);
    
    // Trigger animation
    setTimeout(() => {
        newRow.style.opacity = "1";
        newRow.style.transform = "translateY(0)";
    }, 50);
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("formPengajuan");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Memproses...`;

            fetch(this.action, {
                method: "POST",
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil!",
                        text: "Pengajuan berhasil dibuat.",
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        // Update UI directly without page reload
                        updatePengajuanList(data.pengajuan);
                        
                        // Reset form
                        form.reset();
                        submitButton.disabled = false;
                        submitButton.innerHTML = `<i class="fas fa-save me-2"></i>Submit Pengajuan`;
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: data.message || "Terjadi kesalahan saat memproses pengajuan."
                    });
                    submitButton.disabled = false;
                    submitButton.innerHTML = `<i class="fas fa-save me-2"></i>Submit Pengajuan`;
                }
            })
            .catch(error => {
                console.error("Error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Terjadi kesalahan saat mengirim pengajuan."
                });
                submitButton.disabled = false;
                submitButton.innerHTML = `<i class="fas fa-save me-2"></i>Submit Pengajuan`;
            });
        });
    }
});