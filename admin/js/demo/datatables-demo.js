// Call the dataTables jQuery plugin
$(document).ready(function() {
    $('#dataTable').DataTable({
        "columnDefs": [
            { "orderable": false, "targets": "no-sort" }
        ]
    });
});