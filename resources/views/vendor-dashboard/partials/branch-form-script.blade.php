{{-- The map on the branch form, and a guard against double submits. --}}
@include('partials.location-picker-script')
<script>
    document.querySelectorAll('[data-branch-form]').forEach(form => {
        form.addEventListener('submit', () => {
            document.querySelectorAll('button[type="submit"]').forEach(submit => {
                if (submit.form === form) { submit.disabled = true; submit.classList.add('opacity-60', 'cursor-wait'); }
            });
        });
    });
</script>
