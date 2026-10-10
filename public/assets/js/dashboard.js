document.addEventListener('DOMContentLoaded', () => {
    const pharmacyCards = document.querySelectorAll('.pharmacy-card');

    pharmacyCards.forEach(card => {
        // Heart Icon - Toggle saved state
        const heartBtn = card.querySelector('.heart-icon');
        if (heartBtn) {
            heartBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent bubbling if card has click event
                const icon = this.querySelector('i');
                
                // Toggle between filled (saved) and outline (unsaved)
                if (this.classList.contains('text-red-500')) {
                    // Unsave
                    this.classList.remove('text-red-500');
                    this.classList.add('text-gray-400');
                    icon.classList.remove('fas'); // Solid
                    icon.classList.add('far');    // Outline
                } else {
                    // Save
                    this.classList.add('text-red-500');
                    this.classList.remove('text-gray-400');
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                }
                
                // Animate
                this.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 200);
            });
        }

        // Visit Store Button - Open Maps
        // Target buttons that contain "Visit Store" text
        const buttons = card.querySelectorAll('button');
        let visitBtn = null;
        buttons.forEach(btn => {
            if (btn.textContent.trim() === 'Visit Store') {
                visitBtn = btn;
            }
        });

        if (visitBtn) {
            visitBtn.addEventListener('click', () => {
                const nameEl = card.querySelector('h3');
                // Get location text, assuming it's the text content of the P tag with map-marker
                const mapIcon = card.querySelector('.fa-map-marker-alt');
                let locationText = '';
                
                if (mapIcon && mapIcon.parentElement) {
                    locationText = mapIcon.parentElement.textContent.trim();
                }

                if (nameEl) {
                    const pharmacyName = nameEl.textContent.trim();
                    const query = `${pharmacyName} ${locationText}`;
                    const url = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
                    window.open(url, '_blank');
                }
            });
        }

        // Phone Button - Call action
        const phoneIcon = card.querySelector('.fa-phone-alt');
        if (phoneIcon) {
            const phoneBtn = phoneIcon.closest('button');
            if (phoneBtn) {
                phoneBtn.addEventListener('click', () => {
                    // In a real app, this would use the actual phone number from data
                    // For demo purposes, we'll alert or try a dummy number
                    alert('Initiating call to pharmacy...');
                    // window.location.href = 'tel:+234800000000';
                });
            }
        }
    });

    console.log('Pharmacies JS loaded and listeners attached.');
});
