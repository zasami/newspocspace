<div class="page-header">
  <h1><i class="bi bi-star"></i> Table VIP</h1>
</div>

<div class="d-flex align-items-center gap-2 mb-3">
  <button class="btn btn-sm btn-primary" id="cvAddBtn"><i class="bi bi-plus-lg"></i> Ajouter un résident VIP</button>
</div>

<div id="cvBody">
  <div class="text-center py-4"><span class="spinner"></span></div>
</div>

<!-- Modal ajout VIP -->
<div class="modal fade" id="cvModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Ajouter un résident VIP</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="position-relative">
          <input type="text" class="form-control" id="cvResidentSearch" placeholder="Chercher un résident..." autocomplete="off">
          <div class="cuis-autocomplete-list" id="cvResidentResults"></div>
        </div>
      </div>
    </div>
  </div>
</div>
