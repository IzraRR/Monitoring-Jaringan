@if (session('success'))

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: {!! json_encode(session('success')) !!},
                    timer: 4000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
        });
    </script>
@endif

@if (session('warning'))

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: {!! json_encode(session('warning')) !!},
                    showConfirmButton: true,
                    confirmButtonColor: '#ffc107'
                });
            }
        });
    </script>
@endif

@if (session('error') || session('danger'))
    @php $errorMessage = session('error') ?? session('danger'); @endphp

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Terjadi Kesalahan',
                    text: {!! json_encode($errorMessage) !!},
                    showConfirmButton: true,
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    </script>
@endif

@if ($errors->any())

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Validasi Gagal',
                    text: {!! json_encode($errors->first()) !!},
                    showConfirmButton: true,
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    </script>
@endif
