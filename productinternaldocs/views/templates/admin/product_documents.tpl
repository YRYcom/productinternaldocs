<div class="panel product-tab" id="product-internal-documents">
    <div class="panel-heading internal-docs-main-heading">
        <i class="icon-file-text"></i> Documents internes
    </div>

    <div class="form-wrapper">
        <div class="form-group">
            <label class="control-label col-lg-3 internal-docs-sub-heading">
                <span class="label-tooltip" data-toggle="tooltip" title="Téléversez des documents internes (factures fournisseurs, fiches techniques, etc.)">
                    Ajouter un document
                </span>
            </label>
            <div class="col-lg-9">
                <form id="internal-doc-upload-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="internal-doc-title">Titre du document</label>
                        <input type="text" name="title" id="internal-doc-title" class="form-control" placeholder="Ex: Facture fournisseur Mars 2024" />
                    </div>
                    <div class="form-group">
                        <label for="internal-doc-file">Fichier</label>
                        <input type="file" name="document" id="internal-doc-file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.gif" />
                        <p class="help-block">
                            Formats acceptés : PDF, Word, Excel, Images (JPG, PNG, GIF), Texte. Taille max : 10 MB.
                        </p>
                    </div>
                    <button type="button" id="btn-upload-internal-doc" class="btn btn-default">
                        <i class="icon-upload"></i> Téléverser
                    </button>
                </form>
            </div>
        </div>

        <hr />

        <div class="form-group">
            <label class="control-label col-lg-3 internal-docs-sub-heading">
                Documents
            </label>
            <div class="col-lg-9">
                <div id="internal-documents-list">
                    <p class="text-muted">Chargement...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .internal-docs-main-heading {
        font-size: 20px;
        font-weight: bold;
        padding: 15px;
    }

    .internal-docs-sub-heading {
        font-weight: bold;
        font-size: 14px;
    }

    #internal-documents-list {
        margin-top: 10px;
    }

    .internal-doc-item {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 12px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .internal-doc-info {
        flex: 1;
    }

    .internal-doc-name {
        font-weight: bold;
        color: #333;
    }

    .internal-doc-meta {
        font-size: 12px;
        color: #666;
        margin-top: 4px;
    }

    .internal-doc-actions {
        display: flex;
        gap: 12px;
    }

    .doc-action-btn {
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px;
        font-size: 18px;
        color: #666;
        transition: all 0.2s;
        text-decoration: none !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .doc-action-btn:hover {
        transform: scale(1.2);
        text-decoration: none !important;
    }

    .doc-action-btn:focus,
    .doc-action-btn:active {
        text-decoration: none !important;
        outline: none;
    }

    .doc-download:hover {
        color: #0d6efd;
    }

    .doc-delete:hover {
        color: #dc3545;
    }
</style>

<script type="text/javascript">
$(document).ready(function() {
    var idProduct = {$id_product};
    var moduleDir = '{$module_dir}';
    var adminController = '{$link->getAdminLink("AdminProductInternalDocs", true)|escape:"javascript"}';
    var csrfToken = '{Tools::getAdminTokenLite("AdminProductInternalDocs")|escape:"javascript"}';

    // Charger les documents
    function loadDocuments() {
        $.ajax({
            url: adminController,
            type: 'GET',
            data: {
                action: 'getDocuments',
                id_product: idProduct
            },
            dataType: 'json',
            success: function(response) {
                displayDocuments(response.documents || []);
            },
            error: function() {
                $('#internal-documents-list').html('<p class="alert alert-danger">Erreur lors du chargement des documents</p>');
            }
        });
    }

    // Afficher les documents
    function displayDocuments(documents) {
        var activeDocuments = documents.filter(function(doc) {
            var isDeleted = doc.deleted_at !== null && doc.deleted_at !== '0000-00-00 00:00:00';
            return !isDeleted;
        });

        if (activeDocuments.length === 0) {
            $('#internal-documents-list').html('<p class="text-muted">Aucun document</p>');
            return;
        }

        var html = '';
        activeDocuments.forEach(function(doc) {
            html += '<div class="internal-doc-item">';
            html += '<div class="internal-doc-info">';
            html += '<div class="internal-doc-name">';
            html += '<i class="icon-file-text"></i> ' + (doc.title || doc.original_name);
            html += '</div>';
            html += '<div class="internal-doc-meta">';
            html += 'Taille: ' + formatFileSize(doc.size) + ' | ';
            html += 'Ajouté le: ' + doc.uploaded_at;
            html += '</div>';
            html += '</div>';

            html += '<div class="internal-doc-actions">';
            html += '<a href="' + adminController + '&action=download&id_document=' + doc.id_document + '" class="doc-action-btn doc-download" title="Télécharger">';
            html += '⬇️';
            html += '</a>';
            html += '<button class="doc-action-btn doc-delete btn-delete" data-id="' + doc.id_document + '" title="Supprimer">';
            html += '🗑️';
            html += '</button>';
            html += '</div>';

            html += '</div>';
        });

        $('#internal-documents-list').html(html);
    }

    $('#btn-upload-internal-doc').click(function() {
        var fileInput = $('#internal-doc-file')[0];
        var titleInput = $('#internal-doc-title');

        if (!fileInput.files || fileInput.files.length === 0) {
            alert('Veuillez sélectionner un fichier');
            return;
        }

        var formData = new FormData();
        formData.append('document', fileInput.files[0]);
        formData.append('action', 'upload');
        formData.append('id_product', idProduct);
        formData.append('title', titleInput.val());
        formData.append('token', csrfToken);

        $.ajax({
            url: adminController,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Document téléversé avec succès');
                    fileInput.value = '';
                    titleInput.val('');
                    loadDocuments();
                } else {
                    alert('Erreur: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                console.log('Upload error:', xhr.responseText, status, error);
                var errorMsg = 'Erreur lors du téléversement';
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch(e) {
                    errorMsg += ' (HTTP ' + xhr.status + ': ' + error + ')';
                }
                alert(errorMsg);
            }
        });
    });

    $(document).on('click', '.btn-delete', function() {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce document ?')) {
            return;
        }

        var idDocument = $(this).data('id');

        $.ajax({
            url: adminController,
            type: 'POST',
            data: {
                action: 'delete',
                id_document: idDocument
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Document supprimé');
                    loadDocuments();
                } else {
                    alert('Erreur: ' + response.error);
                }
            }
        });
    });

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    loadDocuments();
});
</script>