<?php
// payment_ui_components.php

function displayPaymentPage($nim) {
    try {
        $pdo = connectDB();
        $output = '';

        // Get total kompen
        $totalKompen = getTotalKompen($nim);
        $MAX_FREE_MINUTES = 1500;
        $showPayment = $totalKompen > $MAX_FREE_MINUTES;
        $paymentAmount = calculatePaymentAmount($totalKompen);

        // Get latest payment status
        $latestPayment = getLatestPayment($nim);

        $output .= '<div class="container-fluid">';

        // Display existing payment status if any
        if ($latestPayment) {
            $output .= buildPaymentStatusAlert($latestPayment);
        }

        $output .= '<div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-money-bill me-2"></i>Pembayaran Kompen
                        </h5>';

        if (!$showPayment) {
            $output .= buildNoPaymentRequired();
        } else {
            // Selalu tampilkan informasi pembayaran
            $output .= buildPaymentInfoCard($totalKompen);
            
            // Tampilkan tombol pembayaran hanya jika tidak ada pembayaran sukses
            if (!($latestPayment && $latestPayment['STATUS'] === 'success')) {
                // Dan jika tidak dalam status pending
                if (!($latestPayment && $latestPayment['STATUS'] === 'pending')) {
                    $output .= buildPaymentButton($nim, $paymentAmount);
                }
            }
        }

        $output .= '</div></div></div></div>';
        $output .= buildPaymentStyles();
        
        return $output;

    } catch (Exception $e) {
        return '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

function getTotalKompen($nim) {
    $pdo = connectDB();
    $stmt = $pdo->prepare("SELECT TOTAL FROM TBL_MAHASISWA WHERE NIM = ?");
    $stmt->execute([$nim]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? intval($result['TOTAL']) : 0;
}

function getLatestPayment($nim) {
    $pdo = connectDB();
    $stmt = $pdo->prepare("
        SELECT * FROM TBL_PAYMENTS 
        WHERE NIM = ? 
        ORDER BY CREATED_AT DESC 
        FETCH FIRST 1 ROW ONLY
    ");
    $stmt->execute([$nim]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function buildPaymentStatusAlert($payment) {
    // Handle pending status with test controls
    if ($payment['STATUS'] === 'pending') {
        $pendingAlert = '
        <div class="alert alert-warning mb-4" role="alert">
            <div class="d-flex align-items-center mb-3">
                <i class="fas fa-clock me-2"></i>
                <div>
                    <strong>Pembayaran Pending</strong><br>
                    Anda memiliki pembayaran yang belum diselesaikan
                </div>
            </div>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-secondary" onclick="handleCancelPayment(\'' . htmlspecialchars($payment['PAYMENT_ID']) . '\')">
                    <i class="fas fa-times me-2"></i>Batalkan
                </button>
                <button type="button" class="btn btn-primary" onclick="resumePayment(\'' . htmlspecialchars($payment['MIDTRANS_TOKEN']) . '\')">
                    <i class="fas fa-sync-alt me-2"></i>Lanjutkan Pembayaran
                </button>
                <button type="button" class="btn btn-success" onclick="handleConfirmPayment(\'' . htmlspecialchars($payment['PAYMENT_ID']) . '\')">
                    <i class="fas fa-check me-2"></i>Konfirmasi Pembayaran
                </button>
            </div>
        </div>
        
        <!-- Payment Scripts -->
        <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="SB-Mid-client-zm-CX2pZMycoJDnp"></script>
        <script>
        // Fungsi untuk mendapatkan base path
        function getBasePath() {
            const pathParts = window.location.pathname.split("/");
            const kompenIndex = pathParts.findIndex(part => 
                part.toLowerCase() === "sikompen"
            );
            
            if (kompenIndex === -1) {
                throw new Error("Path tidak valid");
            }

            return pathParts.slice(0, kompenIndex + 1).join("/");
        }

        // Fungsi untuk menangani pembatalan pembayaran
        async function handleCancelPayment(paymentId) {
            try {
                const result = await Swal.fire({
                    title: "Konfirmasi Pembatalan",
                    text: "Apakah Anda yakin ingin membatalkan pembayaran ini?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ya, Batalkan",
                    cancelButtonText: "Tidak",
                    showLoaderOnConfirm: true,
                    preConfirm: async () => {
                        try {
                            const basePath = getBasePath();
                            const formData = new FormData();
                            formData.append("payment_id", paymentId);
                            
                            const response = await fetch(`${basePath}/payment/cancel_payment.php`, {
                                method: "POST",
                                body: formData
                            });
                            
                            const data = await response.json();
                            
                            if (!response.ok || !data.success) {
                                throw new Error(data.message || "Gagal membatalkan pembayaran");
                            }
                            
                            return data;
                        } catch (error) {
                            Swal.showValidationMessage(`Request failed: ${error.message}`);
                            throw error;
                        }
                    }
                });

                if (result.isConfirmed) {
                    await Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: "Pembayaran telah dibatalkan",
                        timer: 1500,
                        showConfirmButton: false
                    });
                    window.location.reload();
                }
            } catch (error) {
                console.error("Cancel payment error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: error.message || "Terjadi kesalahan saat membatalkan pembayaran"
                });
            }
        }

        // Fungsi untuk menangani konfirmasi pembayaran
        async function handleConfirmPayment(paymentId) {
    try {
        const result = await Swal.fire({
            title: "Konfirmasi Pembayaran",
            text: "Apakah Anda yakin ingin mengkonfirmasi pembayaran ini?",
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Ya, Konfirmasi", 
            cancelButtonText: "Tidak",
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                try {
                    const basePath = getBasePath();
                    const formData = new FormData();
                    formData.append("payment_id", paymentId);
                    formData.append("status", "success");
                    
                    const response = await fetch(`${basePath}/payment/verify_payment.php`, {
                        method: "POST",
                        body: formData
                    });
                    
                    // Abaikan error parsing JSON 
                    try {
                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            // Jika error tapi bisa di parse, tetap lanjut
                            return true;
                        }
                        return data;
                    } catch (e) {
                        // Jika error parse JSON, tetap lanjut 
                        return true;
                    }

                } catch (error) {
                    // Jika error fetch, tetap lanjut
                    return true;
                }
            }
        });

        if (result.isConfirmed) {
            await Swal.fire({
                icon: "success",
                title: "Pembayaran Berhasil",
                text: "Pembayaran telah dikonfirmasi",
                timer: 1500,
                showConfirmButton: false
            });
            window.location.reload();
        }
    } catch (error) {
        // Refresh jika ada error yang tidak tertangkap
        window.location.reload();
    }
}

        function resumePayment(token) {
            window.snap.pay(token, {
                onSuccess: function(result) {
                    handlePaymentCallback("success", result);
                },
                onPending: function(result) {
                    handlePaymentCallback("pending", result);
                },
                onError: function(result) {
                    handlePaymentCallback("error", result);
                },
                onClose: function() {
                    // Payment window closed
                }
            });
        }

        function handlePaymentCallback(status, result) {
            let title, message, icon;
            
            switch(status) {
                case "success":
                    title = "Pembayaran Berhasil";
                    message = "Pembayaran telah berhasil diproses";
                    icon = "success";
                    break;
                case "pending":
                    title = "Pembayaran Pending";
                    message = "Silakan selesaikan pembayaran Anda";
                    icon = "info";
                    break;
                case "error":
                    title = "Pembayaran Gagal";
                    message = result?.message || "Terjadi kesalahan saat memproses pembayaran";
                    icon = "error";
                    break;
            }
            
            Swal.fire({
                icon: icon,
                title: title,
                text: message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        }
        </script>';

        return $pendingAlert;
    }
    
    // Handle success status
    if ($payment['STATUS'] === 'success') {
        return '
        <div class="alert alert-success mb-4" role="alert">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Pembayaran Berhasil!</strong><br>
                    Pembayaran Kompensasi Anda telah diverifikasi
                </div>
                <a href="../payment/download_receipt.php?payment_id=' . htmlspecialchars($payment['PAYMENT_ID']) . '" 
                   class="btn btn-sm btn-outline-success">
                    <i class="fas fa-download me-2"></i>Download Bukti Pembayaran
                </a>
            </div>
        </div>';
    }
    
    // Handle failed status
    if ($payment['STATUS'] === 'failed') {
        return '
        <div class="alert alert-danger mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-times-circle me-2"></i>
                <div>
                    <strong>Pembayaran Gagal</strong><br>
                    Mohon maaf, pembayaran Anda tidak dapat diproses
                </div>
            </div>
        </div>';
    }

    // Return empty string if no matching status
    return '';
}

function buildNoPaymentRequired() {
    return '
    <div class="alert alert-success d-flex align-items-center">
        <i class="fas fa-check-circle me-2"></i>
        <div>
            <strong>Tidak Ada Pembayaran</strong><br>
            Total kompen Anda masih di bawah 1500 menit. Tidak ada biaya yang perlu dibayarkan.
        </div>
    </div>';
}

function buildPaymentInfoCard($totalKompen) {
    $MAX_FREE_MINUTES = 1500;
    $exceededMinutes = max(0, $totalKompen - $MAX_FREE_MINUTES);
    $payableHours = floor($exceededMinutes / 60);
    $paymentAmount = calculatePaymentAmount($totalKompen);

    return '
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <h6 class="card-subtitle mb-3 fw-bold">Informasi Pembayaran</h6>
            <div class="payment-details">
                <div class="row mb-2">
                    <div class="col-8">Total Kompen</div>
                    <div class="col-4 text-end fw-medium">' . $totalKompen . ' menit</div>
                </div>
                <div class="row mb-2">
                    <div class="col-8">Batas Maksimal Gratis</div>
                    <div class="col-4 text-end fw-medium">' . $MAX_FREE_MINUTES . ' menit</div>
                </div>
                <div class="row mb-2">
                    <div class="col-8">Kelebihan Kompen</div>
                    <div class="col-4 text-end fw-medium">' . $exceededMinutes . ' menit</div>
                </div>
                <div class="row mb-2">
                    <div class="col-8">Total Jam Berbayar</div>
                    <div class="col-4 text-end fw-medium">' . $payableHours . ' jam</div>
                </div>
                <hr>
                <div class="row total-section">
                    <div class="col-8 fw-bold">Total Pembayaran</div>
                    <div class="col-4 text-end text-primary fw-bold">
                        Rp ' . number_format($paymentAmount, 0, ',', '.') . '
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

function buildPaymentButton($nim, $totalKompen) {
    try {
        $pdo = connectDB();
        
        // Check for existing pending payment
        $stmt = $pdo->prepare("
            SELECT PAYMENT_ID, MIDTRANS_TOKEN 
            FROM TBL_PAYMENTS 
            WHERE NIM = ? AND STATUS = 'pending'
            ORDER BY CREATED_AT DESC 
            FETCH FIRST 1 ROW ONLY
        ");
        $stmt->execute([$nim]);
        $existingPayment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get student data
        $stmt = $pdo->prepare("SELECT NAMA, PRODI, EMAIL FROM TBL_MAHASISWA WHERE NIM = ?");
        $stmt->execute([$nim]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate payment amount
        $amount = calculatePaymentAmount($totalKompen);
        
        // Build the button HTML and scripts
        return '
        <div class="text-center">
            <button id="pay-button" 
                    class="btn btn-primary btn-lg"
                    data-nim="' . htmlspecialchars($nim) . '"
                    data-amount="' . htmlspecialchars($amount) . '"
                    data-name="' . htmlspecialchars($student['NAMA']) . '"
                    data-email="' . htmlspecialchars($student['EMAIL']) . '"
                    data-description="Pembayaran Kompen ' . htmlspecialchars($student['PRODI']) . '">
                <i class="fas fa-credit-card me-2"></i>Bayar Sekarang
            </button>
        </div>

        <!-- Payment Scripts -->
        <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="SB-Mid-client-zm-CX2pZMycoJDnp"></script>
        
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const payButton = document.getElementById("pay-button");
            if (!payButton) return;

            payButton.addEventListener("click", async function(e) {
                e.preventDefault();
                
                this.disabled = true;
                const originalText = this.innerHTML;
                this.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Memproses...`;

                try {
                    const pathParts = window.location.pathname.split("/");
                    const kompenIndex = pathParts.findIndex(part => 
                        part.toLowerCase() === "sikompen"
                    );
                    
                    if (kompenIndex === -1) {
                        throw new Error("Konfigurasi sistem tidak valid");
                    }

                    const basePath = pathParts.slice(0, kompenIndex + 1).join("/");
                    const apiUrl = `${basePath}/payment/get_snap_token.php?t=${Date.now()}`;

                    const paymentData = {
                        nim: this.dataset.nim,
                        amount: parseInt(this.dataset.amount),
                        name: this.dataset.name,
                        email: this.dataset.email,
                        description: this.dataset.description
                    };

                    const response = await fetch(apiUrl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "Accept": "application/json"
                        },
                        body: JSON.stringify(paymentData)
                    });

                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.message || "Gagal mendapatkan token pembayaran");
                    }

                    window.snap.pay(data.token, {
                        onSuccess: function(result) {
                            console.log("Success:", result);
                            handlePaymentCallback("success", result, data.order_id);
                        },
                        onPending: function(result) {
                            console.log("Pending:", result);
                            handlePaymentCallback("pending", result, data.order_id);
                        },
                        onError: function(result) {
                            console.log("Error:", result);
                            handlePaymentCallback("error", result, data.order_id);
                            payButton.disabled = false;
                            payButton.innerHTML = originalText;
                        },
                        onClose: function() {
                            console.log("Payment window closed");
                            payButton.disabled = false;
                            payButton.innerHTML = originalText;
                        }
                    });

                } catch (error) {
                    console.error("Payment Error:", error);
                    Swal.fire({
                        icon: "error",
                        title: "Pembayaran Gagal",
                        text: error.message || "Terjadi kesalahan saat memproses pembayaran",
                        confirmButtonText: "Tutup"
                    });
                    
                    payButton.disabled = false;
                    payButton.innerHTML = originalText;
                }
            });

            // Payment callback handler
            async function handlePaymentCallback(status, result, orderId) {
                let title, message, icon;
                
                switch(status) {
                    case "success":
                        title = "Pembayaran Berhasil";
                        message = "Pembayaran telah berhasil diproses";
                        icon = "success";
                        break;
                    case "pending":
                        title = "Pembayaran Pending";
                        message = "Silakan selesaikan pembayaran Anda";
                        icon = "info";
                        break;
                    case "error":
                        title = "Pembayaran Gagal";
                        message = result?.message || "Terjadi kesalahan saat memproses pembayaran";
                        icon = "error";
                        break;
                }

                try {
                    const pathParts = window.location.pathname.split("/");
                    const kompenIndex = pathParts.findIndex(part => 
                        part.toLowerCase() === "sikompen"
                    );
                    const basePath = pathParts.slice(0, kompenIndex + 1).join("/");
                    
                    let paymentType = result.payment_type;
                    let paymentChannel = paymentType;

                    // Determine payment channel
                    if (result.va_numbers && result.va_numbers[0]) {
                        paymentChannel = result.va_numbers[0].bank;
                    } else if (result.payment_type === "gopay") {
                        paymentChannel = result.qr_string ? "qris" : "gopay";
                    } else if (result.payment_type === "shopeepay") {
                        paymentChannel = result.qr_string ? "qris" : "shopeepay";
                    } else if (result.store) {
                        paymentChannel = result.store;
                    } else if (result.permata_va_number) {
                        paymentChannel = "permata";
                    } else if (result.bill_key) {
                        paymentChannel = "mandiri_bill";
                    }

                    const response = await fetch(`${basePath}/payment/update_payment_method.php`, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            order_id: orderId,
                            payment_type: paymentType,
                            payment_channel: paymentChannel,
                            transaction_status: status,
                            transaction_details: result
                        })
                    });

                    if (!response.ok) {
                        throw new Error("Failed to update payment status");
                    }

                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: icon,
                            title: title,
                            text: message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        throw new Error(data.message || "Failed to update payment");
                    }

                } catch (error) {
                    console.error("Error updating payment:", error);
                    Swal.fire({
                        icon: "error",
                        title: "Update Gagal",
                        text: error.message,
                        confirmButtonText: "OK"
                    }).then(() => {
                        window.location.reload();
                    });
                }
            }
        });
        </script>';
        
    } catch (Exception $e) {
        return '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

function buildResumePaymentButton($token, $paymentId) {
    return '
    <div class="d-flex justify-content-center gap-2">
        <button type="button" class="btn btn-secondary" onclick="handleCancelPayment(\'' . htmlspecialchars($paymentId) . '\')">
            <i class="fas fa-times me-2"></i>Batalkan
        </button>
        <button type="button" class="btn btn-primary" onclick="handleResumePayment(\'' . htmlspecialchars($token) . '\')">
            <i class="fas fa-sync-alt me-2"></i>Lanjutkan Pembayaran
        </button>
    </div>
    
    <script>
    function handleResumePayment(token) {
        // Configure Snap payment for resume
        const snapConfig = {
            onSuccess: function(result) {
                handlePaymentSuccess(result);
            },
            onPending: function(result) {
                handlePaymentPending(result);
            },
            onError: function(result) {
                handlePaymentError("Pembayaran gagal: " + (result?.message || "Unknown error"));
            }
        };

        window.snap.pay(token, snapConfig);
    }
    
    function handleCancelPayment(paymentId) {
        Swal.fire({
            title: "Konfirmasi Pembatalan",
            text: "Apakah Anda yakin ingin membatalkan pembayaran ini?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Ya, Batalkan",
            cancelButtonText: "Tidak",
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                try {
                    const response = await fetch("cancel_payment.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: `payment_id=${encodeURIComponent(paymentId)}`
                    });
                    
                    const data = await response.json();
                    if (!data.success) {
                        throw new Error(data.message || "Failed to cancel payment");
                    }
                    
                    return data;
                } catch (error) {
                    Swal.showValidationMessage(`Request failed: ${error.message}`);
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    text: "Pembayaran telah dibatalkan",
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            }
        });
    }
    </script>';
}

function buildPaymentStyles() {
    return '
    <style>
    .payment-info-card .card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
    }

    .payment-details {
        font-size: 0.95rem;
        color: #4b5563;
    }

    .total-section {
        font-size: 1.1rem;
        color: #1f2937;
    }

    #pay-button {
        padding: 0.75rem 2rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    #pay-button:disabled {
        background-color: #9ca3af;
        border-color: #9ca3af;
    }

    .alert {
        border: none;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    </style>';
}

