document.addEventListener("DOMContentLoaded", function() {
    let currentPage = 1;
    let filteredJobs = window.allJobs;
    
    const jobList = document.getElementById("jobList");
    const pagination = document.getElementById("pagination");
    const searchInput = document.getElementById("searchInput");
    const supervisorFilter = document.getElementById("supervisorFilter");
    const minutesFilter = document.getElementById("minutesFilter");
    
    function filterJobs() {
        const searchText = searchInput.value.toLowerCase();
        const selectedSupervisor = supervisorFilter.value;
        const selectedMinutes = minutesFilter.value;
        
        filteredJobs = window.allJobs.filter(job => {
            const matchesSearch = !searchText || 
                job.NAMA_PEKERJAAN.toLowerCase().includes(searchText) || 
                job.KODE_PEKERJAAN.toLowerCase().includes(searchText);
                
            const matchesSupervisor = !selectedSupervisor || 
                job.PENANGGUNG_JAWAB === selectedSupervisor;
                
            const matchesMinutes = !selectedMinutes || 
                job.MENIT_PEKERJAAN.toString() === selectedMinutes;
            
            return matchesSearch && matchesSupervisor && matchesMinutes;
        });

        currentPage = 1;
        displayJobs();
    }

    async function loadJobDetails(jobCard, kodePekerjaan) {
        const detailsContainer = jobCard.querySelector('.job-details-container');
        if (!detailsContainer) return;

        try {
            const response = await fetch(`pekerjaan_detail.php?kode=${encodeURIComponent(kodePekerjaan)}`);
            const data = await response.json();
            
            detailsContainer.innerHTML = `
                <div class="mt-3 p-3 bg-light rounded">
                    <h6 class="mb-2">Detail Pekerjaan:</h6>
                    <p class="mb-0">${data.DETAIL_PEKERJAAN}</p>
                </div>
            `;
        } catch (error) {
            detailsContainer.innerHTML = `
                <div class="alert alert-danger mt-3">
                    Gagal memuat detail pekerjaan
                </div>
            `;
        }
    }

    function displayJobs() {
        const startIndex = (currentPage - 1) * window.itemsPerPage;
        const endIndex = startIndex + window.itemsPerPage;
        const paginatedJobs = filteredJobs.slice(startIndex, endIndex);
        
        if (paginatedJobs.length === 0) {
            jobList.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        Tidak ada pekerjaan yang sesuai dengan filter yang dipilih.
                    </div>
                </div>`;
            pagination.style.display = "none";
            return;
        }

        let jobsHtml = "";
        paginatedJobs.forEach(job => {
            const availableSlots = parseInt(job.BATAS_PEKERJA) - parseInt(job.CURRENT_WORKERS);
            jobsHtml += `
                <div class="col-md-4 mb-4 job-item">
                    <div class="card job-card h-100">
                        <div class="card-body">
                            <h5 class="card-title">${job.NAMA_PEKERJAAN}</h5>
                            <h6 class="card-subtitle mb-2 text-muted">Kode: ${job.KODE_PEKERJAAN}</h6>
                            <div class="job-details">
                                <p><i class="fas fa-clock me-2"></i>Durasi: ${job.MENIT_PEKERJAAN} Menit</p>
                                <p><i class="fas fa-users me-2"></i>Pekerja: ${job.CURRENT_WORKERS}/${job.BATAS_PEKERJA}</p>
                                <p class="${availableSlots > 0 ? "text-success" : "text-danger"}">
                                    Slot tersedia: ${availableSlots}
                                </p>
                                <p><i class="fas fa-user-tie me-2"></i>PJ: ${job.PENANGGUNG_JAWAB}</p>
                            </div>
                            <div class="job-details-container">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
        });
        
        jobList.innerHTML = jobsHtml;
        
        // Load details for each job card
        document.querySelectorAll('.job-card').forEach((card, index) => {
            loadJobDetails(card, paginatedJobs[index].KODE_PEKERJAAN);
        });
        
        updatePagination();
    }

    function updatePagination() {
        const totalPages = Math.ceil(filteredJobs.length / window.itemsPerPage);
        
        if (totalPages <= 1) {
            pagination.style.display = "none";
            return;
        }

        pagination.style.display = "flex";
        let paginationHtml = "";

        paginationHtml += `
            <li class="page-item ${currentPage === 1 ? "disabled" : ""}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>`;

        for (let i = 1; i <= totalPages; i++) {
            paginationHtml += `
                <li class="page-item ${currentPage === i ? "active" : ""}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
        }

        paginationHtml += `
            <li class="page-item ${currentPage === totalPages ? "disabled" : ""}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>`;

        pagination.innerHTML = paginationHtml;

        pagination.querySelectorAll(".page-link").forEach(button => {
            button.addEventListener("click", function(e) {
                e.preventDefault();
                const newPage = parseInt(this.dataset.page);
                if (!isNaN(newPage) && newPage > 0) {
                    currentPage = newPage;
                    displayJobs();
                    jobList.scrollIntoView({ behavior: "smooth" });
                }
            });
        });
    }

    searchInput.addEventListener("input", debounce(filterJobs, 300));
    supervisorFilter.addEventListener("change", filterJobs);
    minutesFilter.addEventListener("change", filterJobs);

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    displayJobs();
});