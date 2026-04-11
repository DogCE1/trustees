function filterCategories(category) {
  const listings = document.querySelectorAll(".listing-card");
  listings.forEach((listing) => {
    if (category === "All" || listing.dataset.category === category) {
      listing.style.display = "block";
    } else {
      listing.style.display = "none";
    }
  });
}
