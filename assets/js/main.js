// SWAP-IT Main JavaScript

document.addEventListener('DOMContentLoaded', function () {
    setupNavbar();
    setupDropdowns();
    setupSmoothScroll();
    setupAlerts();
    setupTabs();
    setupImagePreview('photo', 'imagePreview');
    setupPasswordToggles();
    setupPasswordStrength();
    setupCharCounters();
    setupLoadingButtons();
    setupBadgePolling();
    initScrollAnimations();
    initTiltEffect();
    initScrollProgress();
    initRipple();

    staggerAnimation(document.querySelectorAll('.item-card'), 100);
    staggerAnimation(document.querySelectorAll('.offer-card'), 100);
    document.body.classList.add('loaded');
});

// ===== Navbar mobile =====
function setupNavbar() {
    const toggle = document.getElementById('navbarToggle');
    const menu = document.querySelector('.navbar-menu');
    if (!toggle || !menu) return;

    const close = () => {
        menu.classList.remove('active');
        toggle.classList.remove('active');
    };

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.classList.toggle('active');
        toggle.classList.toggle('active');
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.navbar')) close();
    });

    menu.querySelectorAll('.nav-link, .btn').forEach(link => {
        link.addEventListener('click', () => { if (window.innerWidth <= 768) close(); });
    });

    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { if (window.innerWidth > 768) close(); }, 250);
    });
}

// ===== Dropdown user =====
// Dulu klik di mana saja menyetel style display:none yang menimpa :hover,
// sehingga menu Profil/Pengaturan/Logout tidak bisa dibuka lagi di desktop.
function setupDropdowns() {
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        if (!toggle) return;
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const open = dropdown.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    document.addEventListener('click', function (e) {
        document.querySelectorAll('.dropdown.open').forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
                const t = dropdown.querySelector('.dropdown-toggle');
                if (t) t.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
        }
    });
}

// ===== Smooth scroll (abaikan href="#" kosong yang dulu memicu error) =====
function setupSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (!href || href === '#') return;
            let target = null;
            try { target = document.querySelector(href); } catch (err) { return; }
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

// ===== Alert hilang otomatis (kecuali yang ditandai data-persist / error) =====
function setupAlerts() {
    document.querySelectorAll('.alert').forEach(alert => {
        if (alert.hasAttribute('data-persist') || alert.classList.contains('alert-error')) return;
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
}

// ===== Tabs =====
function setupTabs() {
    const buttons = document.querySelectorAll('.tab-btn');
    const contents = document.querySelectorAll('.tab-content');
    buttons.forEach(button => {
        button.addEventListener('click', function () {
            buttons.forEach(b => b.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const target = document.getElementById(this.dataset.tab + '-tab');
            if (target) target.classList.add('active');

            // Simpan tab aktif di URL supaya tetap sama setelah reload / aksi
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', this.dataset.tab);
                history.replaceState(null, '', url);
            } catch (e) { /* abaikan */ }
        });
    });
}

// ===== Preview foto + validasi ukuran =====
function setupImagePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (!input || !preview) return;

    input.addEventListener('change', function (e) {
        const file = e.target.files[0];
        preview.innerHTML = '';
        preview.style.display = 'none';
        if (!file) return;

        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran file terlalu besar. Maksimal 5MB.');
            input.value = '';
            return;
        }
        if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
            alert('Hanya file JPG, PNG, GIF, atau WEBP yang diperbolehkan.');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (ev) {
            const img = document.createElement('img');
            img.src = ev.target.result;
            img.alt = 'Preview';
            preview.appendChild(img);
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });
}

// ===== Tampilkan / sembunyikan password =====
function setupPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            this.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            this.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        });
    });
}

// ===== Indikator kekuatan password =====
function setupPasswordStrength() {
    document.querySelectorAll('input[data-strength]').forEach(input => {
        const output = document.getElementById(input.dataset.strength);
        if (!output) return;
        input.addEventListener('input', function () {
            const pw = this.value;
            let score = 0;
            if (pw.length >= 6) score++;
            if (pw.length >= 10) score++;
            if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
            if (/[0-9]/.test(pw)) score++;
            if (/[^a-zA-Z0-9]/.test(pw)) score++;

            if (!pw) {
                output.textContent = '';
                output.className = 'password-strength';
            } else if (score <= 1) {
                output.textContent = 'Lemah';
                output.className = 'password-strength weak';
            } else if (score <= 3) {
                output.textContent = 'Sedang';
                output.className = 'password-strength medium';
            } else {
                output.textContent = 'Kuat';
                output.className = 'password-strength strong';
            }
        });
    });
}

// ===== Penghitung karakter =====
function setupCharCounters() {
    document.querySelectorAll('[data-counter]').forEach(field => {
        const counter = document.getElementById(field.dataset.counter);
        const max = parseInt(field.getAttribute('maxlength') || '0', 10);
        if (!counter || !max) return;
        const update = () => { counter.textContent = field.value.length + ' / ' + max; };
        field.addEventListener('input', update);
        update();
    });
}

// ===== Status loading di tombol submit =====
function setupLoadingButtons() {
    document.querySelectorAll('form:not([data-no-loading])').forEach(form => {
        form.addEventListener('submit', function (e) {
            // Jangan kunci tombol jika submit dibatalkan (mis. confirm() dijawab "Batal")
            if (e.defaultPrevented) return;
            const btn = this.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.dataset.originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            }
        });
    });

    // Tombol kembali normal saat halaman dibuka lagi lewat tombol Back
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[data-original-html]').forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalHtml;
        });
    });
}

// ===== Badge navbar diperbarui berkala =====
function setupBadgePolling() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;

    // Laravel: data-counts-url. Versi PHP native: data-base-url + api/get_unread_count.php
    let url = navbar.dataset.countsUrl || '';
    if (!url && navbar.dataset.loggedIn === '1' && navbar.dataset.baseUrl) {
        url = navbar.dataset.baseUrl + 'api/get_unread_count.php';
    }
    if (!url) return;

    const setBadge = (name, count) => {
        const badge = document.querySelector('.nav-link[data-badge="' + name + '"] .badge');
        if (!badge) return;
        badge.textContent = count;
        badge.style.display = count > 0 ? '' : 'none';
    };

    async function refresh() {
        if (document.hidden) return;
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success) return;
            setBadge('messages', data.messages_count);
            setBadge('offers', data.offers_count);
            setBadge('notifications', data.notifications_count);
        } catch (e) { /* offline, coba lagi nanti */ }
    }

    setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
}

// ===== Animasi =====
function initScrollAnimations() {
    if (!('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -100px 0px' });

    document.querySelectorAll('.item-card, .offer-card, .conversation-card, .form-container').forEach(el => observer.observe(el));
}

function initTiltEffect() {
    if (window.matchMedia('(hover: none)').matches) return;
    document.querySelectorAll('.item-card, .offer-card').forEach(card => {
        card.addEventListener('mousemove', function (e) {
            const rect = this.getBoundingClientRect();
            const rotateX = (e.clientY - rect.top - rect.height / 2) / 30;
            const rotateY = (rect.width / 2 - (e.clientX - rect.left)) / 30;
            this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.01, 1.01, 1.01)`;
        });
        card.addEventListener('mouseleave', function () {
            this.style.transform = '';
        });
    });
}

function initScrollProgress() {
    const bar = document.createElement('div');
    bar.className = 'scroll-progress';
    document.body.appendChild(bar);
    window.addEventListener('scroll', () => {
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        bar.style.width = (height > 0 ? (window.pageYOffset / height) * 100 : 0) + '%';
    }, { passive: true });
}

function initRipple() {
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function (event) {
            const rect = button.getBoundingClientRect();
            const diameter = Math.max(button.clientWidth, button.clientHeight);
            const ripple = document.createElement('span');
            ripple.style.width = ripple.style.height = diameter + 'px';
            ripple.style.left = (event.clientX - rect.left - diameter / 2) + 'px';
            ripple.style.top = (event.clientY - rect.top - diameter / 2) + 'px';
            ripple.classList.add('ripple');
            const old = button.querySelector('.ripple');
            if (old) old.remove();
            button.appendChild(ripple);
        });
    });
}

function staggerAnimation(elements, delay) {
    elements.forEach((el, i) => { el.style.animationDelay = (i * delay) + 'ms'; });
}

// Toast (dipakai bila perlu dari halaman lain)
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    const icon = document.createElement('i');
    icon.className = 'fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle');
    const text = document.createElement('span');
    text.textContent = message;
    toast.append(icon, text);
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
