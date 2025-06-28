<header>
    <div class="navbar-container">
        <div class="logo">
            <a href="index.php"><h1>Pulka</h1></a>
        </div>

        <div class="nav-links" id="navMenu">
            <form id="searchForm">
                <input type="text" id="searchQuery" placeholder="Buscar por destino o nombre...">
                <button type="submit" id="searchButton">Buscar</button>
            </form>

            <a href="login.html"><button class="InicioBtn">Inicio de Sesión</button></a>
            <a href="registro.html"><button class="RegistroBtn">Registrarse</button></a>
        </div>

        <!-- Botón hamburguesa -->
        <button class="menu-toggle" onclick="toggleMenu()">☰</button>
    </div>
</header>

<script>
    function toggleMenu() {
        const nav = document.getElementById('navMenu');
        nav.classList.toggle('active');
    }
</script>