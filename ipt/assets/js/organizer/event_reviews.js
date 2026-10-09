function openSidebar() {

    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.add('open');

    overlay.classList.add('show');

}


function closeSidebar() {

    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.remove('open');

    overlay.classList.remove('show');

}


document.querySelectorAll(
    '.sidebar-menu a'
).forEach(function(link) {

    link.addEventListener(
        'click',
        function() {

            if (
                window.innerWidth <= 991
            ) {

                closeSidebar();

            }

        }
    );

});


document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {

            closeSidebar();

        }

    }
);
