document.addEventListener('DOMContentLoaded', function () {
  const sidebar = document.getElementById('sidebar');
  const content = document.getElementById('content');
  const btnNavbarToggle = document.getElementById('sidebarCollapse');
  const btnFloatingToggle = document.getElementById('sidebarToggleFloat');

  function toggleSidebar() {
    if (!sidebar || !content) return;
    sidebar.classList.toggle('active');
    content.classList.toggle('active');
  }

  [btnNavbarToggle, btnFloatingToggle]
    .filter(Boolean)
    .forEach(btn => btn.addEventListener('click', toggleSidebar));

  // Close sidebar when a link is clicked on small screens
  const isSmallScreen = () => window.innerWidth < 992; // Bootstrap lg breakpoint
  sidebar?.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      if (isSmallScreen() && sidebar.classList.contains('active')) {
        toggleSidebar();
      }
    });
  });

  // Ensure correct initial state on load/resize
  function handleResize() {
    if (!sidebar || !content) return;
    if (isSmallScreen()) {
      // sidebar hidden by default on small screens
      if (!sidebar.classList.contains('active')) {
        sidebar.classList.add('active');
        content.classList.add('active');
      }
    } else {
      // expanded on desktop by default
      sidebar.classList.remove('active');
      content.classList.remove('active');
    }
  }

  window.addEventListener('resize', handleResize);
  handleResize();
});
