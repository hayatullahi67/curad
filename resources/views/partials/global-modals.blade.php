@if(session('success') || session('error'))
<script>
    // Auto-dismiss alerts after 5 seconds if they exist
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-auto-dismiss');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>
@endif

<!-- Global Delete Modal -->
<x-modal id="globalDeleteModal" max-width="sm" :z-index="100">
    <div class="text-center">
        <div class="w-20 h-20 bg-red-50 text-red-500 rounded-[2rem] flex items-center justify-center mx-auto mb-6 shadow-sm">
            <i class="fas fa-trash-alt text-3xl"></i>
        </div>
        <h3 id="deleteModalTitle" class="text-2xl font-black text-gray-900 tracking-tight mb-2">Delete Item?</h3>
        <p id="deleteModalBody" class="text-gray-400 font-bold uppercase text-[10px] tracking-widest mb-8">This action cannot be undone.</p>

        <form id="globalDeleteForm" method="POST" action="">
            @csrf
            @method('DELETE')
            <div class="flex flex-col space-y-3">
                <button type="submit" class="w-full bg-red-500 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg shadow-red-200 hover:bg-red-600 transition-all active:scale-95">
                    Yes, Delete It
                </button>
                <button type="button" data-cura-modal-close class="w-full py-4 text-gray-400 font-black text-[10px] uppercase tracking-widest hover:text-gray-600 transition-all">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</x-modal>

<!-- Global Edit/Action Modal Shell -->
<!-- Use openEditModal(url) to load content via AJAX, or populate innerHTML manually -->
<x-modal id="globalEditModal" max-width="2xl" :z-index="90">
    <x-slot:title><span id="editModalTitle">Edit Item</span></x-slot:title>
    <x-slot:subtitle><span id="editModalSubtitle">Make changes below</span></x-slot:subtitle>

    <div class="relative">
        <!-- Loading State -->
        <div id="globalEditLoading" class="hidden absolute inset-0 bg-white z-50 flex flex-col items-center justify-center">
            <div class="w-16 h-16 border-4 border-blue-100 border-t-blue-600 rounded-full animate-spin mb-4"></div>
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Content...</p>
        </div>

        <div id="globalEditContent">
            <!-- Content will be injected here -->
        </div>
    </div>
</x-modal>

<script>
    // === Delete Modal Logic ===
    const deleteForm = document.getElementById('globalDeleteForm');
    const deleteTitle = document.getElementById('deleteModalTitle');
    const deleteBody = document.getElementById('deleteModalBody');

    window.openDeleteModal = function(actionUrl, title = 'Delete Item?', body = 'This action cannot be undone.') {
        deleteForm.action = actionUrl;
        deleteTitle.innerText = title;
        deleteBody.innerText = body;
        CuraModal.open('globalDeleteModal');
    };

    window.closeDeleteModal = function() {
        CuraModal.close('globalDeleteModal');
    };

    // === Edit Modal Logic ===
    const editContent = document.getElementById('globalEditContent');
    const editLoading = document.getElementById('globalEditLoading');
    const editTitle = document.getElementById('editModalTitle');

    window.openEditModal = function(title = 'Edit Item', contentHtml = null, fetchUrl = null) {
        editTitle.innerText = title;
        CuraModal.open('globalEditModal');

        if (contentHtml) {
            editContent.innerHTML = contentHtml;
            editLoading.classList.add('hidden');
        } else if (fetchUrl) {
            editLoading.classList.remove('hidden');
            fetch(fetchUrl)
                .then(response => response.text())
                .then(html => {
                    editContent.innerHTML = html;
                    editLoading.classList.add('hidden');
                })
                .catch(err => {
                    editContent.innerHTML = '<p class="text-red-500 text-center py-10 font-bold">Error loading content.</p>';
                    editLoading.classList.add('hidden');
                });
        }
    };

    window.closeEditModal = function() {
        CuraModal.close('globalEditModal');
        setTimeout(() => { editContent.innerHTML = ''; }, 300);
    };
</script>
