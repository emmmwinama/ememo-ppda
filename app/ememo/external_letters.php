<div class="container mt-4">
  <h4 class="mb-4">📨 External Letters Received</h4>
  
  <div id="lettersContainer" class="table-responsive">
    <table class="table table-bordered table-hover">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Reference</th>
          <th>Title</th>
          <th>From</th>
          <th>Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="lettersTableBody">
        <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
function loadExternalLetters() {
  $.get('get_external_letters.php', function (res) {
    let rows = '';
    if (res.success && res.data.length) {
      res.data.forEach((letter, index) => {
        rows += `
          <tr>
            <td>${index + 1}</td>
            <td>${letter.reference_number}</td>
            <td>${letter.title}</td>
            <td>${letter.received_from}</td>
            <td>${letter.received_date}</td>
            <td><span class="badge bg-secondary">${letter.status}</span></td>
            <td>
              <a href="external_view.php?id=${letter.id}" class="btn btn-sm btn-outline-success">📄 View</a>
            </td>
          </tr>
        `;
      });
    } else {
      rows = '<tr><td colspan="7" class="text-center text-muted">No external letters found.</td></tr>';
    }

    $('#lettersTableBody').html(rows);
  }).fail(() => {
    $('#lettersTableBody').html('<tr><td colspan="7" class="text-danger text-center">❌ Failed to load data.</td></tr>');
  });
}

$(document).ready(loadExternalLetters);
</script>
