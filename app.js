document.addEventListener("DOMContentLoaded", () => {
  const offersContainer = document.getElementById("offers");

  const ofertas = [
    { titulo: "Lorem Ipsum", precio: "$500,00", ubicacion: "Argentina", descuento: true },
    { titulo: "Lorem Ipsum", precio: "$2000,00", ubicacion: "Argentina", descuento: true },
    { titulo: "Lorem Ipsum", precio: "$1500,00", ubicacion: "Argentina", descuento: false },
    { titulo: "Lorem Ipsum", precio: "$700,00", ubicacion: "Argentina", descuento: false },
  ];

  ofertas.forEach(oferta => {
    const card = document.createElement("div");
    card.className = "card";

    card.innerHTML = `
      <img src="https://via.placeholder.com/300x160" alt="${oferta.titulo}" />
      <div class="card-body">
        <h3>${oferta.titulo}</h3>
        <p>${oferta.ubicacion}</p>
        <p class="price">${oferta.precio}${oferta.descuento ? " <span style='color:lime'>(En oferta)</span>" : ""}</p>
        <button onclick="reservar('${oferta.titulo}')">Reservar</button>
      </div>
    `;
    offersContainer.appendChild(card);
  });

  document.getElementById("searchForm").addEventListener("submit", e => {
    e.preventDefault();
    alert("Función de búsqueda pendiente de conectar a backend PHP/Node");
  });
});

function reservar(titulo) {
  alert(`Reservaste: ${titulo}`);
  // Aquí puedes integrar con Node.js/PHP usando fetch o axios
}
