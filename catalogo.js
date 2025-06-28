// catalogo.js
document.addEventListener("DOMContentLoaded", async () => {
  const offersContainer = document.getElementById("offers");
  const API_BASE_URL = 'http://localhost/tu-proyecto/api.php'; // ASEGÚRATE QUE ESTA URL SEA CORRECTA PARA TU SERVIDOR WEB
  // Por ejemplo, si tu api.php está directamente en la raíz de tu servidor (ej. htdocs de XAMPP),
  // podría ser 'http://localhost/api.php'. Si está en una subcarpeta como 'booking_app',
  // sería 'http://localhost/booking_app/api.php'. Ajusta según tu configuración.

  let paquetesTuristicos = [];

  // Función para mostrar loading
  function mostrarLoading() {
      offersContainer.innerHTML = `
          <div class="loading">
              <h3>Cargando paquetes turísticos...</h3>
              <div class="spinner"></div>
          </div>
      `;
  }

  // Función para mostrar error
  function mostrarError(mensaje) {
      offersContainer.innerHTML = `
          <div class="error">
              <h3>Error al cargar los paquetes</h3>
              <p>${mensaje}</p>
              <button onclick="cargarPaquetes()">Reintentar</button>
          </div>
      `;
  }

  // Función para obtener imagen según destino
  function obtenerImagenDestino(destino) {
      const imagenes = {
          'El Calafate, Argentina': 'https://images.unsplash.com/photo-1544550285-f813152fb2fd?w=300&h=160&fit=crop',
          'Cancún, México': 'https://images.unsplash.com/photo-1512813195386-6cf811ad3542?w=300&h=160&fit=crop',
          'París, Francia': 'https://images.unsplash.com/photo-1502602898536-47ad22581b52?w=300&h=160&fit=crop',
          'Serengeti, Tanzania': 'https://images.unsplash.com/photo-1516426122078-c23e76319801?w=300&h=160&fit=crop',
          'Cusco, Perú': 'https://images.unsplash.com/photo-1587595431973-160d0d94add1?w=300&h=160&fit=crop',
          'Santorini, Grecia': 'https://images.unsplash.com/photo-1570077188670-e3a8d69ac5ff?w=300&h=160&fit=crop',
          'La Fortuna, Costa Rica': 'https://images.unsplash.com/photo-1605538883669-825200433431?w=300&h=160&fit=crop',
          'Marrakech, Marruecos': 'https://images.unsplash.com/photo-1539650116574-75c0c6d90bf4?w=300&h=160&fit=crop'
      };
      return imagenes[destino] || 'https://via.placeholder.com/300x160';
  }

  // Función para formatear precio
  function formatearPrecio(precio) {
      return `$${parseFloat(precio).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  // Función para determinar si hay descuento
  function tieneDescuento(precio) {
      return precio < 1000; // Asumiendo que precios bajos son "en oferta"
  }

  // Función para cargar paquetes desde la API
  async function cargarPaquetes() {
      try {
          mostrarLoading();
          
          // La ruta es API_BASE_URL porque ya apunta a api.php. Luego le concatenamos el endpoint.
          const response = await fetch(`${API_BASE_URL}/paquetes`); 
          
          if (!response.ok) {
              throw new Error(`Error HTTP: ${response.status}`);
          }
          
          const data = await response.json();
          paquetesTuristicos = data;
          
          if (paquetesTuristicos.length === 0) {
              offersContainer.innerHTML = `
                  <div class="no-results">
                      <h3>No hay paquetes disponibles</h3>
                      <p>Vuelve pronto para ver nuevas ofertas</p>
                  </div>
              `;
              return;
          }
          
          mostrarPaquetes(paquetesTuristicos);
          
      } catch (error) {
          console.error('Error al cargar paquetes:', error);
          mostrarError(error.message);
      }
  }

  // Función para mostrar paquetes en el DOM
  function mostrarPaquetes(paquetes) {
      offersContainer.innerHTML = '';
      
      paquetes.forEach(paquete => {
          const card = document.createElement("div");
          card.className = "card";
          
          const descuento = tieneDescuento(paquete.precio_base);
          const precioFormateado = formatearPrecio(paquete.precio_base);
          
          card.innerHTML = `
              <img src="${obtenerImagenDestino(paquete.destino)}" alt="${paquete.nombre}" />
              <div class="card-body">
                  <h3>${paquete.nombre}</h3>
                  <p class="destination">${paquete.destino}</p>
                  <p class="description">${paquete.descripcion}</p>
                  ${paquete.servicios_adicionales && paquete.servicios_adicionales.length > 0 ? `
                      <div class="services">
                          <h4>Servicios adicionales disponibles:</h4>
                          <ul>
                              ${paquete.servicios_adicionales.map(servicio => 
                                  `<li><strong>${servicio.tipo}:</strong> ${servicio.descripcion} (+${formatearPrecio(servicio.precio)})</li>`
                              ).join('')}
                          </ul>
                      </div>
                  ` : ''}
                  <p class="price">
                      ${precioFormateado}
                      ${descuento ? " <span class='discount'>(¡En oferta!)</span>" : ""}
                  </p>
                  <button onclick="window.location.href='reserva.php?id=${paquete.id}'" 
                          ${!paquete.disponible ? 'disabled' : ''}>
                      ${paquete.disponible ? 'Reservar' : 'No disponible'}
                  </button>
              </div>
          `;
          offersContainer.appendChild(card);
      });
  }

  // Función de búsqueda con API
  async function buscarPaquetes(query) {
      try {
          mostrarLoading();
          
          const response = await fetch(`${API_BASE_URL}/buscar?q=${encodeURIComponent(query)}`);
          
          if (!response.ok) {
              throw new Error(`Error HTTP: ${response.status}`);
          }
          
          const data = await response.json();
          
          if (data.length === 0) {
              offersContainer.innerHTML = `
                  <div class="no-results">
                      <h3>No se encontraron resultados para: "${query}"</h3>
                      <p>Intenta con otros términos de búsqueda</p>
                      <button onclick="cargarPaquetes()">Ver todos los paquetes</button>
                  </div>
              `;
              return;
          }
          
          mostrarPaquetes(data);
          
      } catch (error) {
          console.error('Error al buscar paquetes:', error);
          mostrarError('Error al realizar la búsqueda: ' + error.message);
      }
  }

  // Event listener para el formulario de búsqueda
  document.getElementById("searchForm").addEventListener("submit", e => {
      e.preventDefault();
      const query = e.target.query.value.trim();
      
      if (query === '') {
          cargarPaquetes();
          return;
      }
      
      buscarPaquetes(query);
  });

  // Cargar paquetes al iniciar
  await cargarPaquetes();
});

// La función 'reservar' no es necesaria en catalogo.js,
// ya que la redirección a reserva.php maneja la lógica de reserva.
// Si esta función se usaba para una reserva directa sin pasar por reserva.php,
// debería ser eliminada o su lógica movida a reserva.php
