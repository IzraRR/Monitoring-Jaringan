@extends('layouts.app')

@section('title', 'Pencatatan Pembayaran | SMKN 53')
@section('page_heading', 'Dashboard | Pencatatan Pembayaran & Tagihan')

@section('content')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<style>
    .payment-title {
        color: #19395f;
        font-size: 2.1rem;
        font-weight: 800;
        letter-spacing: 0.2px;
        margin-bottom: 12px;
        text-transform: uppercase;
    }

    .payment-card {
        background: #21476f;
        color: #0f172a;
        border-radius: 8px;
        border: 2px solid #1f2937;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
    }

    .payment-card .card-title {
        font-size: 1.2rem;
        line-height: 1.2;
        color: #0b1525;
        font-weight: 800;
        text-transform: uppercase;
        margin-bottom: 14px;
    }

    .payment-card label,
    .payment-card .muted-line {
        color: #0e1826;
        font-weight: 600;
    }

    .payment-card .form-control,
    .payment-card .form-select {
        border: 2px solid #1f2937;
        border-radius: 8px;
        background: #d8d8d8;
        color: #0f172a;
        box-shadow: none;
    }

    .payment-card .action-btn,
    .payment-card .secondary-btn {
        border: 2px solid #1f2937;
        border-radius: 8px;
        background: #d8d8d8;
        color: #111827;
        font-weight: 800;
    }

    .payment-table {
        border: 3px solid #1f2937;
        background: #f8fbfb;
    }

    .payment-table thead th {
        background: #c7c7c7;
        border: 3px solid #1f2937;
        color: #111827;
        font-weight: 800;
        vertical-align: middle;
        padding: 1rem 0.75rem;
    }

    .payment-table tbody td {
        border: 3px solid #1f2937;
        padding: 1.2rem 0.75rem;
        color: #111827;
        background: #f5fbfb;
    }

    .table-footer-bar {
        border: 3px solid #1f2937;
        border-top: 0;
        background: #f5fbfb;
        padding: 14px 18px;
    }
</style>

<h1 class="payment-title">Pencatatan Pembayaran & Tagihan</h1>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card payment-card h-100">
            <div class="card-body p-3 p-md-4">
                <div class="card-title">Rekam Pembayaran Baru</div>

                <form action="{{ route('pembayaran.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <select name="id_pelanggan" id="pelanggan-select" class="form-select" required>
                            <option value="">Pilih pelanggan</option>
                            @foreach($pelangganOptions as $pelanggan)
                                <option value="{{ $pelanggan->id_pelanggan }}" data-harga="{{ $pelanggan->paket->harga ?? 0 }}" data-paket="{{ $pelanggan->paket->nama_paket ?? '-' }}" data-masa-aktif="{{ optional($pelanggan->masa_aktif)->format('Y-m-d') }}" @selected((string) old('id_pelanggan') === (string) $pelanggan->id_pelanggan)>
                                    {{ $pelanggan->nama_pelanggan }} ({{ $pelanggan->username_mikrotik }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="text-muted small mb-3" id="client-info">Client Info: Pilih pelanggan untuk melihat paket dan total tagihan</div>

                    <div class="row align-items-end g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label mb-1">Periode</label>
                            <select id="periode-select" name="periode_tagihan" class="form-select" required>
                                <option value="">Pilih pelanggan terlebih dahulu</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Nominal (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-2 border-dark fw-bold">Rp</span>
                                <input type="text" id="nominal-input" name="nominal" class="form-control fw-bold" inputmode="numeric" autocomplete="off" value="{{ old('nominal') ? number_format((float) old('nominal'), 0, ',', '.') : '' }}" placeholder="-" readonly required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-dark w-100 fw-bold py-2">PROSES PEMBAYARAN</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card payment-card h-100">
            <div class="card-body p-3 p-md-4">
                <div class="card-title">Kontrol Notifikasi (WA)</div>
                <form action="{{ route('pembayaran.send-notifications') }}" method="POST" class="mb-3">
                    @csrf
                    <button type="submit" class="btn secondary-btn w-100 py-2">KIRIM NOTIFIKASI TAGIHAN</button>
                </form>

                <small class="text-muted d-block mt-2">Generate & Kirim via WhatsApp</small>
                <div class="mt-2 p-2 bg-light border rounded" style="max-height: 150px; overflow-y: auto;">
                    <small class="text-success fw-bold d-block" style="white-space: pre-line;">Log status:
{{ session('terminal_log') ?? '-' }}</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card payment-table mb-3">
    <div class="card-body py-3 px-4 border-bottom bg-white">
        <form id="payment-search-form" action="{{ route('pembayaran.index') }}" method="GET" class="row g-2 align-items-end">
            <div class="col-12">
                <input type="text" id="q" name="q" value="{{ $search }}" class="form-control" placeholder="Cari nama pelanggan, username, periode, atau status notifikasi">
                <small id="payment-search-indicator" class="text-muted mt-1 d-none">Mencari...</small>
            </div>
        </form>
    </div>
    <div id="payment-table-container">
        <div class="table-responsive">
            <table class="table mb-0 text-center align-middle">
                <thead>
                    <tr>
                        <th style="width: 10%">ID Pembayaran</th>
                        <th style="width: 10%">ID Pelanggan</th>
                        <th style="width: 16%">Nama Pelanggan</th>
                        <th style="width: 10%">Periode</th>
                        <th style="width: 12%">Tanggal Bayar</th>
                        <th style="width: 12%">Nominal (Rp)</th>
                        <th style="width: 12%">Status Notifikasi</th>
                        <th style="width: 18%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pembayaran as $item)
                        <tr>
                            <td>PMB_{{ str_pad((string) $item->id_pembayaran, 3, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $item->id_pelanggan }}</td>
                            <td>{{ $item->pelanggan->nama_pelanggan ?? '-' }}</td>
                            <td>{{ $item->periode_tagihan }}</td>
                            <td>{{ optional($item->tanggal_bayar)->translatedFormat('d F Y') ?? '-' }}</td>
                            <td>{{ number_format($item->nominal, 0, ',', '.') }}</td>
                            <td>{{ $item->status_notifikasi }}</td>
                            <td>
                                <a href="{{ route('pembayaran.struk', $item->id_pembayaran) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Struk Pembayaran">
                                    <i class="bi bi-printer"></i> Cetak PDF
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-4 text-muted">Belum ada data pembayaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-footer-bar">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-center gap-3">
                <div class="fw-bold fs-5 text-dark">Menampilkan {{ $pembayaran->firstItem() ?? 0 }} - {{ $pembayaran->lastItem() ?? 0 }} dari {{ $pembayaran->total() }} rekaman</div>
                <div>{{ $pembayaran->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pelangganSelect = document.getElementById('pelanggan-select');
        const periodeSelect = document.getElementById('periode-select');
        const nominalInput = document.getElementById('nominal-input');
        const clientInfo = document.getElementById('client-info');
        const paymentForm = document.querySelector('form[action="{{ route('pembayaran.store') }}"]');
        const searchForm = document.getElementById('payment-search-form');
        const searchInput = document.getElementById('q');
        const searchIndicator = document.getElementById('payment-search-indicator');
        const paymentTableContainer = document.getElementById('payment-table-container');
        const oldPeriodeValue = @json(old('periode_tagihan'));

        function formatYearMonth(dateObject) {
            const year = dateObject.getFullYear();
            const month = String(dateObject.getMonth() + 1).padStart(2, '0');
            return `${year}-${month}`;
        }

        function formatIndonesianMonth(dateObject) {
            return new Intl.DateTimeFormat('id-ID', {
                month: 'long',
                year: 'numeric',
            }).format(dateObject);
        }

        function formatIndonesianNumber(value) {
            const numericValue = Number(String(value || '').replace(/\D/g, ''));
            if (!Number.isFinite(numericValue) || numericValue <= 0) {
                return '';
            }

            return new Intl.NumberFormat('id-ID', {
                maximumFractionDigits: 0,
            }).format(numericValue);
        }

        function extractNumericValue(value) {
            const digitsOnly = String(value || '').replace(/\D/g, '');
            return digitsOnly === '' ? '' : digitsOnly;
        }

        function renderPeriodeOptions(masaAktifValue, selectedValue = '') {
            if (!periodeSelect) {
                return;
            }

            const parsedMasaAktif = parseDateYmd(masaAktifValue);
            if (!parsedMasaAktif) {
                periodeSelect.innerHTML = '<option value="">Pilih pelanggan terlebih dahulu</option>';
                periodeSelect.value = '';
                return;
            }

            const startDate = new Date(parsedMasaAktif.getFullYear(), parsedMasaAktif.getMonth() + 1, 1);
            const options = ['<option value="">Pilih periode</option>'];

            for (let index = 0; index < 12; index++) {
                const optionDate = new Date(startDate.getFullYear(), startDate.getMonth() + index, 1);
                const optionLabel = formatIndonesianMonth(optionDate);
                const monthCount = index + 1;
                const isSelected = selectedValue ? selectedValue === optionLabel : index === 0;
                options.push(`<option value="${optionLabel}" data-months="${monthCount}" ${isSelected ? 'selected' : ''}>${optionLabel}</option>`);
            }

            periodeSelect.innerHTML = options.join('');
        }

        function updateNominalFromPeriode() {
            if (!periodeSelect || !nominalInput) {
                return;
            }

            const selectedOption = pelangganSelect.options[pelangganSelect.selectedIndex];
            const harga = parseFloat(selectedOption?.dataset.harga || '0');
            const periodOption = periodeSelect.options[periodeSelect.selectedIndex];
            const monthCount = Number(periodOption?.dataset.months || '1');

            if (harga > 0 && monthCount > 0) {
                const totalNominal = harga * monthCount;
                nominalInput.value = formatIndonesianNumber(totalNominal);
            } else {
                nominalInput.value = '';
            }
        }

        function getNextMonthValueFromDate(dateObject) {
            const nextMonthDate = new Date(dateObject.getFullYear(), dateObject.getMonth() + 1, 1);
            return formatIndonesianMonth(nextMonthDate);
        }

        function parseDateYmd(dateString) {
            if (!dateString) {
                return null;
            }

            const parts = String(dateString).split('-');
            if (parts.length < 3) {
                return null;
            }

            const year = Number(parts[0]);
            const month = Number(parts[1]);
            const day = Number(parts[2]);

            if (!year || !month || !day) {
                return null;
            }

            return new Date(year, month - 1, day);
        }

        function resolvePeriodeValue(masaAktifValue) {
            const today = new Date();

            if (!masaAktifValue) {
                return getNextMonthValueFromDate(today);
            }

            const parsedMasaAktif = parseDateYmd(masaAktifValue);
            if (!parsedMasaAktif || Number.isNaN(parsedMasaAktif.getTime()) || parsedMasaAktif < today) {
                return getNextMonthValueFromDate(today);
            }

            return getNextMonthValueFromDate(parsedMasaAktif);
        }

        function updateClientInfo(preferredPeriodeValue = '') {
            const selectedOption = pelangganSelect.options[pelangganSelect.selectedIndex];
            if (selectedOption.value === '') {
                clientInfo.textContent = 'Pilih pelanggan untuk melihat paket dan total tagihan';
                if (periodeSelect) {
                    periodeSelect.innerHTML = '<option value="">Pilih pelanggan terlebih dahulu</option>';
                }
                if (nominalInput) {
                    nominalInput.value = '';
                }
                return;
            }

            const paket = selectedOption.dataset.paket || '-';
            const harga = parseFloat(selectedOption.dataset.harga || '0');
            clientInfo.textContent = `Paket: ${paket} | Estimasi Tagihan: Rp ${harga.toLocaleString('id-ID')}`;
            renderPeriodeOptions(selectedOption.dataset.masaAktif || '', preferredPeriodeValue || oldPeriodeValue || '');
            updateNominalFromPeriode();
        }

         if (periodeSelect) {
            periodeSelect.addEventListener('change', updateNominalFromPeriode);
        }

        if (paymentForm && nominalInput) {
            paymentForm.addEventListener('submit', function () {
                nominalInput.value = extractNumericValue(nominalInput.value);
            });
        }

        pelangganSelect?.addEventListener('change', function () {
            updateClientInfo('');
        });
        updateClientInfo(oldPeriodeValue || '');

        function setSearchIndicatorLoading(isLoading) {
            if (!searchIndicator) {
                return;
            }

            searchIndicator.classList.toggle('d-none', !isLoading);
        }

        async function refreshPaymentTableByUrl(url) {
            if (!searchForm || !paymentTableContainer) {
                return;
            }

            paymentTableContainer.classList.add('opacity-50');
            setSearchIndicatorLoading(true);

            try {
                const response = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const nextTableContainer = doc.getElementById('payment-table-container');

                if (nextTableContainer) {
                    paymentTableContainer.innerHTML = nextTableContainer.innerHTML;
                    window.history.replaceState({}, '', url.pathname + url.search);
                }
            } catch (error) {
                console.error('Gagal memuat data pembayaran:', error);
            } finally {
                paymentTableContainer.classList.remove('opacity-50');
                setSearchIndicatorLoading(false);
            }
        }

        async function refreshPaymentTable(query) {
            const url = new URL(searchForm.action, window.location.origin);
            const cleanQuery = query.trim();

            if (cleanQuery !== '') {
                url.searchParams.set('q', cleanQuery);
            }

            url.searchParams.delete('page');

            await refreshPaymentTableByUrl(url);
        }

        if (paymentTableContainer) {
            paymentTableContainer.addEventListener('click', function (event) {
                const paginationLink = event.target.closest('.pagination a');
                if (!paginationLink) {
                    return;
                }

                event.preventDefault();

                const url = new URL(paginationLink.href, window.location.origin);
                const cleanQuery = (searchInput?.value || '').trim();
                if (cleanQuery !== '') {
                    url.searchParams.set('q', cleanQuery);
                }

                refreshPaymentTableByUrl(url);
            });
        }

        if (searchInput) {
            let searchDebounceTimer = null;
            let lastSubmittedValue = searchInput.value;

            searchInput.addEventListener('input', function () {
                clearTimeout(searchDebounceTimer);
                setSearchIndicatorLoading(true);

                searchDebounceTimer = setTimeout(function () {
                    const currentValue = searchInput.value;
                    if (currentValue === lastSubmittedValue) {
                        setSearchIndicatorLoading(false);
                        return;
                    }

                    lastSubmittedValue = currentValue;
                    refreshPaymentTable(currentValue);
                }, 350);
            });
        }
    });
</script>
@endpush