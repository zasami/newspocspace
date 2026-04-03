/**
 * Terrassière Website — Scripts
 */

// Navbar scroll effect
const nav = document.getElementById('wsNav');
window.addEventListener('scroll', () => {
  nav.classList.toggle('scrolled', window.scrollY > 30);
});

// Mobile menu toggle
const toggle = document.getElementById('wsNavToggle');
const links = document.getElementById('wsNavLinks');
toggle.addEventListener('click', () => {
  links.classList.toggle('open');
  toggle.querySelector('i').className = links.classList.contains('open') ? 'bi bi-x-lg' : 'bi bi-list';
});

// Close mobile menu on link click
links.querySelectorAll('a').forEach(a => {
  a.addEventListener('click', () => {
    links.classList.remove('open');
    toggle.querySelector('i').className = 'bi bi-list';
  });
});

// Active nav link on scroll
const sections = document.querySelectorAll('section[id]');
const navLinks = links.querySelectorAll('a[href^="#"]');
function updateActiveLink() {
  const scrollY = window.scrollY + 100;
  sections.forEach(section => {
    const top = section.offsetTop;
    const height = section.offsetHeight;
    const id = section.getAttribute('id');
    if (scrollY >= top && scrollY < top + height) {
      navLinks.forEach(l => l.classList.remove('active'));
      const active = links.querySelector(`a[href="#${id}"]`);
      if (active) active.classList.add('active');
    }
  });
}
window.addEventListener('scroll', updateActiveLink);
updateActiveLink();

// Scroll animations (IntersectionObserver)
const animItems = document.querySelectorAll(
  '.ws-card, .ws-service-card, .ws-timeline-item, .ws-module-card, .ws-team-card, .ws-value-card, .ws-contact-item, .ws-coverage-banner, .ws-team-cta, .ws-contact-form, .ws-video-card, .ws-video-divider-content, .ws-menu-day, .ws-anim-card'
);
animItems.forEach(el => el.classList.add('ws-animate'));

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

animItems.forEach(el => observer.observe(el));

// Hero video playlist (zasamix-style rotation)
document.querySelectorAll('[data-playlist]').forEach(video => {
  try {
    const list = JSON.parse(video.dataset.playlist || '[]');
    if (list.length < 2) return;
    let idx = 0;
    video.addEventListener('ended', () => {
      idx = (idx + 1) % list.length;
      video.src = list[idx];
      video.play();
    });
  } catch { /* ignore parse errors */ }
});

// Lazy play videos when visible (save bandwidth)
const videoObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    const video = entry.target;
    if (entry.isIntersecting) {
      if (video.paused) video.play().catch(() => {});
    } else {
      video.pause();
    }
  });
}, { threshold: 0.2 });

document.querySelectorAll('.ws-video-divider video, .ws-video-card video').forEach(v => {
  v.pause();
  videoObserver.observe(v);
});

// Contact form — sends to backend
const form = document.getElementById('contactForm');
if (form) {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Envoi...';
    btn.disabled = true;
    try {
      const fd = new FormData(form);
      const data = Object.fromEntries(fd.entries());
      data.action = 'contact_submit';
      const res = await fetch('/spocspace/website/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).then(r => r.json());
      if (res.success) {
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Message envoyé !';
        btn.style.background = '#16A34A';
        form.reset();
      } else {
        btn.innerHTML = '<i class="bi bi-x-lg"></i> ' + (res.message || 'Erreur');
        btn.style.background = '#dc2626';
      }
    } catch(err) {
      btn.innerHTML = '<i class="bi bi-x-lg"></i> Erreur réseau';
      btn.style.background = '#dc2626';
    }
    setTimeout(() => { btn.innerHTML = origHTML; btn.disabled = false; btn.style.background = ''; }, 3000);
  });
}

// Side widget — reveal after scrolling past hero
const sideWidget = document.getElementById('wsSideWidget');
if (sideWidget) {
  const heroEl = document.getElementById('hero');
  function checkWidget() {
    const threshold = heroEl ? heroEl.offsetHeight * 0.4 : 300;
    sideWidget.classList.toggle('visible', window.scrollY > threshold);
  }
  window.addEventListener('scroll', checkWidget, { passive: true });
  checkWidget();
}
