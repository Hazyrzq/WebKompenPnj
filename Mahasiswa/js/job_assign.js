function initializeFormPengajuan() {
    const elements = {
      form: document.getElementById("formPengajuan"),
      totalWaktu: document.getElementById("totalWaktu"),
      submitBtn: document.getElementById("submitPengajuan"),
      selectedPJ: document.getElementById("selectedPJ"),
      selectedPJName: document.getElementById("selectedPJName"),
      progressBar: document.getElementById("progressBar"),
      filterPengawas: document.getElementById("filterPengawasNew"),
      filterJam: document.getElementById("filterJamNew"),
      resetFilters: document.getElementById("resetFiltersNew"),
      container: document.querySelector(".job-cards-container .row"),
      pagination: document.querySelector(".pagination-container"),
    };
    if (!elements.form) return;
  
    const state = {
      pj: null,
      totalJam: 0,
      selected: new Map(),
      jobs: null,
      loading: false,
      sisaKompen: parseInt(document.getElementById("sisaKompen").value),
      perPage: 6,
      page: 1,
    };
  
    // Core Functions
    const initializeAll = async () => {
      // Initialize sisa waktu display
      const sisaMenitElement = document.getElementById('sisaMenit');
      if (sisaMenitElement) {
          sisaMenitElement.textContent = formatWaktu(state.sisaKompen);
      }
      
      attachFormHandlers();
      attachCheckboxes();
      if (elements.filterPengawas && elements.filterJam) {
          await initFilters();
      }
    };
  
    const attachFormHandlers = () => {
      elements.form.addEventListener("submit", handleSubmit);
      if (elements.resetFilters) {
        elements.resetFilters.addEventListener("click", () => {
          elements.filterPengawas.value = "";
          elements.filterJam.value = "";
          state.page = 1;
          state.jobs && displayJobs(state.jobs);
        });
      }
    };
  
    const attachCheckboxes = () => {
      document.querySelectorAll(".job-card").forEach((card) => {
        if (card.dataset.disabled === "true") return;
  
        card.removeEventListener("click", handleCardClick);
        card.addEventListener("click", handleCardClick);
  
        const kode = card.dataset.kode;
        const saved = state.selected.get(kode);
        if (saved?.checked) {
          card.classList.add("selected");
        }
      });
      updateTotalAndProgress();
    };
  
    const handleCardClick = (e) => {
      const card = e.currentTarget;
      const kode = card.dataset.kode;
      const menit = parseInt(card.dataset.menit);
      const isSelected = card.classList.contains("selected");
  
      if (!isSelected) {
        // Cek Pengawas yang sama
        const currentPJ = Array.from(state.selected.values()).find((data) => data.checked)?.pj;
  
        if (currentPJ && currentPJ !== card.dataset.pj) {
          showAlert("Anda hanya dapat memilih pekerjaan dengan pengawas yang sama", "warning");
          return;
        }
  
        state.selected.set(kode, {
          jam: card.dataset.jam,
          menit: card.dataset.menit,
          pj: card.dataset.pj,
          checked: true,
        });
        card.classList.add("selected");
      } else {
        state.selected.delete(kode);
        card.classList.remove("selected");
      }
      updateTotalAndProgress();
    };
  
    // Filter Functions
    const initFilters = async () => {
      try {
        await loadAllJobs();
        setupFilters();
        attachFilterEvents();
      } catch (err) {
        console.error(err);
        showAlert("Gagal memuat data filter", "error");
      }
    };
  
    const loadAllJobs = async () => {
      if (state.loading) return;
      state.loading = true;
      showLoading();
  
      try {
        const lastPage = parseInt(document.querySelector(".pagination .page-item:nth-last-child(2) .page-link").textContent);
        const promises = Array.from({ length: lastPage }, (_, i) => fetchPage(i + 1));
        const results = await Promise.all(promises);
        state.jobs = results.flat();
        displayJobs(state.jobs);
        return true;
      } catch (err) {
        console.error(err);
        showAlert("Gagal memuat data", "error");
        return false;
      } finally {
        state.loading = false;
        hideLoading();
      }
    };
  
    const fetchPage = async (page) => {
      const response = await fetch(`?page=new-pengajuan&form_page=${page}&ajax=1&_=${Date.now()}`);
      if (!response.ok) throw new Error("Network error");
      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, "text/html");
      return Array.from(doc.querySelectorAll(".job-card")).map((card) => {
        const div = document.createElement("div");
        div.className = "col-md-6 mb-4";
        div.appendChild(card.cloneNode(true));
        return div.outerHTML;
      });
    };
  
    const setupFilters = () => {
      if (!state.jobs) return;
      const unique = {
        pengawas: new Set(),
        jam: new Set(),
      };
  
      const div = document.createElement("div");
      state.jobs.forEach((job) => {
        div.innerHTML = job;
        unique.pengawas.add(div.querySelector(".info-item span").textContent.trim());
        unique.jam.add(div.querySelector(".info-item:last-child span").textContent.split(" ")[0]);
      });
  
      populateSelect(elements.filterPengawas, [...unique.pengawas].sort(), false);
      populateSelect(
        elements.filterJam,
        [...unique.jam].sort((a, b) => Number(a) - Number(b)),
        true
      );
    };
  
    const populateSelect = (select, values, isJam) => {
      if (!select) return;
      select.innerHTML = `<option value="">Semua ${isJam ? "Menit" : "Pengawas"}</option>`;
      values.forEach((val) => {
        const opt = document.createElement("option");
        opt.value = val;
        // Jika ini adalah selector jam, konversi dan format waktunya
        if (isJam) {
          const menit = parseInt(val);
          opt.textContent = formatWaktu(menit);
        } else {
          opt.textContent = val;
        }
        select.appendChild(opt);
      });
    };
  
    const attachFilterEvents = () => {
      elements.filterPengawas?.addEventListener("change", applyFilters);
      elements.filterJam?.addEventListener("change", applyFilters);
    };
  
    const applyFilters = () => {
      if (!state.jobs) return;
      const pj = elements.filterPengawas.value;
      const jam = elements.filterJam.value;
      saveSelections();
      const filtered = filterJobs(pj, jam);
      displayJobs(filtered);
      restoreSelections();
    };
  
    const filterJobs = (pj, jam) => {
      const div = document.createElement("div");
      return state.jobs.filter((job) => {
        div.innerHTML = job;
        const cardPJ = div.querySelector(".info-item span").textContent.trim();
        const cardTimeText = div.querySelector(".info-item:last-child span").textContent;
        const cardMenit = parseInt(cardTimeText.split(" ")[0]); // Mengambil angka menit
        return (!pj || cardPJ === pj) && (!jam || cardMenit.toString() === jam);
      });
    };
  
    // Display Functions
    const displayJobs = (jobs) => {
      if (!elements.container) return;
      if (!jobs.length) {
        elements.container.innerHTML = `
                  <div class="col-12">
                      <div class="alert alert-info text-center my-4">
                          <i class="fas fa-info-circle me-2"></i>Tidak ada pekerjaan yang sesuai
                      </div>
                  </div>`;
        elements.pagination.style.display = "none";
        return;
      }
  
      const pages = Math.ceil(jobs.length / state.perPage);
      const start = (state.page - 1) * state.perPage;
      elements.container.innerHTML = jobs.slice(start, start + state.perPage).join("");
      updatePagination(pages, jobs);
      attachCheckboxes();
    };
  
    const updatePagination = (total, jobs) => {
      if (total <= 1) {
        elements.pagination.style.display = "none";
        return;
      }
  
      elements.pagination.innerHTML = generatePaginationHTML(total);
      elements.pagination.querySelector(".pagination").addEventListener("click", (e) => {
        const link = e.target.closest(".page-link");
        if (!link || link.closest(".disabled")) return;
        e.preventDefault();
        const newPage = parseInt(link.dataset.page);
        if (newPage > 0 && newPage <= total) {
          state.page = newPage;
          displayJobs(jobs);
          window.scrollTo({ top: 0, behavior: "smooth" });
        }
      });
      elements.pagination.style.display = "";
    };
  
    // Helper Functions
    const updateTotalAndProgress = () => {
        let actualTotalMenit = 0;
        let currentPJ = null;
        let valid = true;
    
        state.selected.forEach((data, code) => {
            if (!data.checked) return;
    
            if (!currentPJ) {
                currentPJ = data.pj;
                state.pj = data.pj;
                elements.selectedPJName.textContent = data.pj;
                elements.selectedPJ.value = data.pj;
            } else if (currentPJ !== data.pj) {
                valid = false;
                const box = document.querySelector(`input[value="${code}"]`);
                if (box) box.checked = false;
                state.selected.delete(code);
                showAlert("Pilih pekerjaan dengan pengawas yang sama", "warning");
                return;
            }
            actualTotalMenit += parseInt(data.menit);
        });
    
        if (!state.selected.size) {
            state.pj = null;
            elements.selectedPJName.textContent = "-";
            elements.selectedPJ.value = "";
        }
    
        // Tampilkan total waktu sebenarnya yang dipilih
        elements.totalWaktu.textContent = formatWaktu(actualTotalMenit);
    
        // Hitung progress bar (capped at 100%)
        const percent = Math.min((actualTotalMenit / MAX_MENIT_PENGAJUAN) * 100, 100);
        elements.progressBar.style.width = `${percent}%`;
        elements.progressBar.className = `progress-bar ${percent > 70 ? "bg-warning" : "bg-success"}`;
    
        // Untuk perhitungan sisa, gunakan nilai yang di-cap ke MAX_MENIT_PENGAJUAN
        const totalForSisa = Math.min(actualTotalMenit, MAX_MENIT_PENGAJUAN);
        const sisaWaktu = Math.max(state.sisaKompen - totalForSisa, 0);
        
        const sisaMenitElement = document.getElementById('sisaMenit');
        if (sisaMenitElement) {
            sisaMenitElement.textContent = formatWaktu(sisaWaktu);
            sisaMenitElement.className = sisaWaktu === 0 ? 'text-danger' : 'text-success';
        }
    
        // Update status tombol submit
        elements.submitBtn.disabled = !valid || actualTotalMenit === 0;
    }
  
    // Modifikasi fungsi handleSubmit
    const handleSubmit = async (e) => {
        e.preventDefault();
    
        let totalMenit = 0;
        state.selected.forEach((data) => {
          if (data.checked) {
            totalMenit += parseInt(data.menit);
          }
        });
    
        if (totalMenit === 0) {
          showAlert("Pilih minimal satu pekerjaan!", "warning");
          return;
        }
    
        elements.submitBtn.disabled = true;
        const btnText = elements.submitBtn.innerHTML;
        elements.submitBtn.innerHTML = `<div class="d-flex align-items-center">
            <span class="spinner-border spinner-border-sm me-2"></span>
            <span>Memproses...</span>
        </div>`;
    
        try {
          const formData = new FormData(elements.form);
          formData.delete("pekerjaan[]");
          state.selected.forEach((data, code) => {
            if (data.checked) formData.append("pekerjaan[]", code);
          });
    
          const response = await fetch(elements.form.action, {
            method: "POST",
            body: formData,
          });
    
          const data = await response.json();
          if (data.success) {
            state.selected.clear();
            await Swal.fire({
              icon: "success",
              title: "Berhasil!",
              text: "Pengajuan berhasil dibuat.",
              showConfirmButton: false,
              timer: 1500,
            });
            const timestamp = new Date().getTime();
            window.location.href = `?page=pengajuan&t=${timestamp}`;
          } else {
            throw new Error(data.message || "Gagal memproses pengajuan.");
          }
        } catch (error) {
          console.error(error);
          showAlert(error.message || "Gagal mengirim pengajuan.", "error");
        } finally {
          elements.submitBtn.disabled = false;
          elements.submitBtn.innerHTML = btnText;
        }
      };
    
  
    const showAlert = (message, type) => {
      Swal.fire({
        icon: type,
        title: type === "error" ? "Error" : "Peringatan",
        text: message,
        timer: 3000,
        showConfirmButton: false,
      });
    };
  
    const showLoading = () => {
      elements.container.innerHTML = `
              <div class="col-12">
                  <div class="text-center my-4">
                      <div class="spinner-border text-primary"></div>
                      <p class="mt-2">Memuat data...</p>
                  </div>
              </div>`;
    };
  
    const hideLoading = () => {
      document.querySelector(".loading-indicator")?.remove();
    };
  
    const saveSelections = () => {
      document.querySelectorAll(".pekerjaan-checkbox:checked").forEach((box) => {
        state.selected.set(box.value, {
          jam: box.dataset.jam,
          pj: box.dataset.pj,
          checked: true,
        });
      });
    };
  
    const restoreSelections = () => {
      state.selected.forEach((data, code) => {
        const box = document.querySelector(`input[value="${code}"]`);
        if (box && data.checked) box.checked = true;
      });
      updateTotalAndProgress();
    };
  
    const generatePaginationHTML = (total) => `
          <nav aria-label="Page navigation">
              <ul class="pagination justify-content-center">
                  <li class="page-item${state.page <= 1 ? " disabled" : ""}">
                      <a class="page-link" data-page="${state.page - 1}" href="javascript:void(0)">
                          <i class="fas fa-chevron-left"></i>
                      </a>
                  </li>
                  ${Array.from(
                    { length: total },
                    (_, i) => `
                      <li class="page-item${state.page === i + 1 ? " active" : ""}">
                          <a class="page-link" data-page="${i + 1}" href="javascript:void(0)">${i + 1}</a>
                      </li>
                  `
                  ).join("")}
                  <li class="page-item${state.page >= total ? " disabled" : ""}">
                      <a class="page-link" data-page="${state.page + 1}" href="javascript:void(0)">
                          <i class="fas fa-chevron-right"></i>
                      </a>
                  </li>
              </ul>
          </nav>`;
  
  // Initialize
  initializeAll();
}

const MAX_MENIT_PENGAJUAN = 1500;

function formatWaktu(menit) {
  const jam = Math.floor(menit / 60);
  return `${menit} menit (${jam} jam)`;
}

document.addEventListener("DOMContentLoaded", initializeFormPengajuan);
  // Helper function to show warnings
  function showWarning(message) {
    const Toast = Swal.mixin({
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.addEventListener("mouseenter", Swal.stopTimer);
        toast.addEventListener("mouseleave", Swal.resumeTimer);
      },
    });
  
    Toast.fire({
      icon: "warning",
      title: message,
    });
  }
  
  // Function to handle job application
  function ajukanPekerjaan(kodePekerjaan) {
    Swal.fire({
      title: "Konfirmasi",
      text: "Apakah Anda yakin ingin mengajukan pekerjaan ini?",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Ya",
      cancelButtonText: "Tidak",
    }).then((result) => {
      if (result.isConfirmed) {
        fetch("process_pengajuan.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: "kode_pekerjaan=" + encodeURIComponent(kodePekerjaan),
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              Swal.fire({
                icon: "success",
                title: "Berhasil!",
                text: "Pengajuan berhasil dibuat.",
                showConfirmButton: false,
                timer: 1500,
              }).then(() => {
                const timestamp = new Date().getTime();
                window.location.href = `?page=pengajuan&t=${timestamp}`;
              });
            } else {
              throw new Error(data.message || "Terjadi kesalahan saat membuat pengajuan");
            }
          })
          .catch((error) => {
            console.error("Error:", error);
            Swal.fire({
              icon: "error",
              title: "Gagal",
              text: error.message || "Terjadi kesalahan saat membuat pengajuan",
            });
          });
      }
    });
  }
  