        </main>

        <footer class="pie-pagina">
            &copy; <?= date('Y') ?> <?= e(NOMBRE_SISTEMA) ?> · Proyecto de vinculación académica municipal.
        </footer>
    </div>
</div>

<script>
    // Menú lateral responsive (mobile-first)
    const botonMenu = document.getElementById('botonMenuMovil');
    const sidebar = document.getElementById('sidebar');
    if (botonMenu && sidebar) {
        botonMenu.addEventListener('click', () => sidebar.classList.toggle('sidebar-abierta'));
        document.addEventListener('click', (evento) => {
            if (window.innerWidth <= 900 && sidebar.classList.contains('sidebar-abierta')
                && !sidebar.contains(evento.target) && !botonMenu.contains(evento.target)) {
                sidebar.classList.remove('sidebar-abierta');
            }
        });
    }

    // Confirmación genérica para acciones destructivas (eliminar)
    document.querySelectorAll('.confirmar-eliminacion').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            const mensaje = formulario.dataset.mensaje || '¿Está seguro de que desea eliminar este registro? Esta acción no se puede deshacer.';
            if (!confirm(mensaje)) {
                evento.preventDefault();
            }
        });
    });
</script>
</body>
</html>
