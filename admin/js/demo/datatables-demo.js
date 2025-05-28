// แก้ไข JavaScript ส่วนของ delete confirmation
$(document).ready(function() {
    // Initialize DataTable
    $('#dataTable').DataTable({
        "columnDefs": [
            { "orderable": false, "targets": "no-sort" }
        ]
    });

    // Use event delegation for delete button
    $('#dataTable').on('click', '.delete-btn', function(e) {
        e.preventDefault();
        const deleteId = $(this).data('id');
        Swal.fire({
            title: 'คุณแน่ใจหรือไม่?',
            text: "คุณต้องการลบประเภทนี้หรือไม่? การดำเนินการนี้ไม่สามารถย้อนกลับได้!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete=' + deleteId;
            }
        });
    });
});