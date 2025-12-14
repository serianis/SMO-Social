document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mainContent = document.querySelector('.main-content');

    sidebarToggle.addEventListener('click', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.toggle('collapsed');
        } else {
            sidebar.classList.toggle('active');
        }
    });

    // Navigation Switching
    const navLinks = document.querySelectorAll('.sidebar-nav a');
    const views = document.querySelectorAll('.view-container');
    const pageTitle = document.getElementById('page-title');

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links
            document.querySelectorAll('.sidebar-nav li').forEach(li => li.classList.remove('active'));
            this.parentElement.classList.add('active');

            // Update Page Title
            const viewName = this.getAttribute('data-view');
            pageTitle.textContent = this.querySelector('.nav-label').textContent;

            // Show corresponding view (for now just console log as we only have dashboard)
            console.log('Switching to view:', viewName);
            
            // In a real app, we would hide all views and show the target one
            // views.forEach(view => view.classList.remove('active'));
            // document.getElementById(`view-${viewName}`).classList.add('active');
        });
    });

    // Dark Mode Toggle
    const themeToggle = document.getElementById('theme-toggle');
    const body = document.body;
    const themeIcon = themeToggle.querySelector('.dashicons');

    // Check for saved preference
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        body.classList.remove('light-mode');
        themeIcon.classList.replace('dashicons-moon', 'dashicons-sun');
    }

    themeToggle.addEventListener('click', function() {
        if (body.classList.contains('light-mode')) {
            body.classList.replace('light-mode', 'dark-mode');
            themeIcon.classList.replace('dashicons-moon', 'dashicons-sun');
            localStorage.setItem('theme', 'dark');
        } else {
            body.classList.replace('dark-mode', 'light-mode');
            themeIcon.classList.replace('dashicons-sun', 'dashicons-moon');
            localStorage.setItem('theme', 'light');
        }
    });

    // Mock Chart Rendering (Simple CSS-based bars for prototype)
    const chartContainer = document.getElementById('main-chart');
    renderMockChart(chartContainer);

    function renderMockChart(container) {
        container.innerHTML = '';
        container.style.display = 'flex';
        container.style.alignItems = 'flex-end';
        container.style.justifyContent = 'space-between';
        container.style.padding = '20px';
        container.style.gap = '10px';

        const data = [45, 60, 35, 70, 55, 80, 65];
        const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        data.forEach((value, index) => {
            const barGroup = document.createElement('div');
            barGroup.style.display = 'flex';
            barGroup.style.flexDirection = 'column';
            barGroup.style.alignItems = 'center';
            barGroup.style.flex = '1';
            barGroup.style.height = '100%';
            barGroup.style.justifyContent = 'flex-end';

            const bar = document.createElement('div');
            bar.style.width = '60%';
            bar.style.height = `${value}%`;
            bar.style.backgroundColor = 'var(--primary-color)';
            bar.style.borderRadius = '4px 4px 0 0';
            bar.style.transition = 'height 0.5s ease';

            const label = document.createElement('span');
            label.textContent = days[index];
            label.style.fontSize = '12px';
            label.style.marginTop = '8px';
            label.style.color = 'var(--text-secondary)';

            barGroup.appendChild(bar);
            barGroup.appendChild(label);
            container.appendChild(barGroup);
        });
    }
});
