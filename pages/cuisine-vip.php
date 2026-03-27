<div class="page-header">
  <h1><i class="bi bi-star"></i> Table VIP</h1>
  <p class="text-muted small">Tapez <kbd>@</kbd> + nom ou numéro de chambre dans la barre de recherche pour ajouter un résident</p>
</div>

<!-- Search inline -->
<div style="max-width:400px;margin-bottom:1rem;position:relative">
  <input type="text" class="form-control" id="cvSearch" placeholder="@nom ou @chambre pour chercher un résident..." autocomplete="off" style="border-radius:12px;border:1px solid #E8E5E0;padding:.6rem 1rem .6rem 2.5rem;font-size:.88rem">
  <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aaa;font-size:.9rem"></i>
  <div class="cuis-autocomplete-list" id="cvSearchResults" style="position:absolute;left:0;right:0;top:100%;z-index:20"></div>
</div>

<div id="cvBody">
  <div class="text-center py-4"><span class="spinner"></span></div>
</div>
