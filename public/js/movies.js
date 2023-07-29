document.getElementById('searchTermInput').addEventListener('keypress', function (event) {
    if (event.key === "Enter" || event.keyCode === 13) {
        event.preventDefault();
        document.getElementById('directSearchButton').click();
    }
});

const searchSortBySelect = document.getElementById('searchSortBySelect');
const searchSortOrderSelect = document.getElementById('searchSortOrderSelect');
const searchGenreSelect = document.getElementById('searchGenreSelect');
const searchLanugageSelect = document.getElementById('searchLanguageSelect');
const searchReleaseYearSelect = document.getElementById('searchReleaseYearSelect');
const searchPerPageSelect = document.getElementById('searchPerPageSelect');

function search() {
    let sortBy = searchSortBySelect.value
    let sortOrder = searchSortOrderSelect.value
    let searchGenre = searchGenreSelect.value
    let searchLanguage = searchLanugageSelect.value
    let searchReleaseYear = searchReleaseYearSelect.value
    let searchPerPage = searchPerPageSelect.value
    let searchTerm = document.getElementById('searchTermInput').value

    let getParameters = '?'

    getParameters += 'sb=' + sortBy
    getParameters += '&so=' + sortOrder
    getParameters += '&pp=' + searchPerPage

    if (searchGenre != '') {
        getParameters += '&ge=' + searchGenre
    }
    if (searchLanguage != '') {
        getParameters += '&la=' + searchLanguage
    }
    if (searchReleaseYear != '') {
        getParameters += '&ry=' + searchReleaseYear
    }
    if (searchTerm != '') {
        getParameters += '&s=' + searchTerm
    }

    const urlWithoutGetParameters = window.location.href.split('?')[0];

    window.location.href = urlWithoutGetParameters + getParameters;
}


function resetSearchOptions() {
    searchSortBySelect.value = 'title'
    searchSortOrderSelect.value = 'asc'
    searchGenreSelect.value = ''
    searchLanugageSelect.value = ''
    searchReleaseYearSelect.value = ''
    searchPerPageSelect.value = '24'
}

function setSortOptionsCookie() {
    document.getElementById("saveSortingOptionsButton").setAttribute("disabled", "");

    var date = new Date();
    var expiration = date.setTime(date.getTime() + 60 * 60 * 24 * 365);
    document.cookie = "movie-sort-order=" + searchSortOrderSelect.value + ";expires=" + expiration + ";path=/";
    document.cookie = "movie-sort-by=" + searchSortBySelect.value + ";expires=" + expiration + ";path=/";
}

function enableSaveSortingOptionsButton() {
    var saveSortingOptionsButton = document.getElementById("saveSortingOptionsButton");
    if (saveSortingOptionsButton.hasAttribute("disabled")) {
        saveSortingOptionsButton.removeAttribute("disabled");
    }
}
