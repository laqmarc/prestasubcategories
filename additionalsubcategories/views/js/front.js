/**
 * Additional Subcategories - Frontend Script
 * Injects additional categories if theme doesn't support the native hook
 */

document.addEventListener("DOMContentLoaded", function() {
    console.log('DEBUG: AdditionalSubcategories JS loaded');
    
    // Variables are injected via Media::addJsDef in PHP
    if (typeof additionalCats === 'undefined' || !additionalCats || additionalCats.length === 0) {
        console.log('DEBUG: No additionalCats variable or empty');
        return;
    }

    console.log('DEBUG: Found ' + additionalCats.length + ' additional categories', additionalCats);

    var $list = document.querySelector("#subcategories .subcategories-list");
    
    if (!$list) {
        console.log('DEBUG: Selector #subcategories .subcategories-list not found');
        return;
    }

    console.log('DEBUG: Found subcategories list element', $list);

    // Check if categories are already rendered (avoid duplicates)
    additionalCats.forEach(function(cat) {
        var exists = !!$list.querySelector('a[href*="id_category=' + cat.id_category + '"]') || 
                     !!$list.querySelector('a[href*="/' + cat.id_category + '-"]');
        
        if (!exists) {
            console.log('DEBUG: Injecting category: ' + cat.name);
            
            var categoryLink = baseUri + 'index.php?id_category=' + cat.id_category + '&controller=category';
            var imageUrl = imgCatDir + cat.id_category + '-category_default.jpg';
            
            var html = "<li>" +
                "<div class=\"subcategory-image\">" +
                    "<a href=\"" + categoryLink + "\" title=\"" + cat.name + "\" class=\"img\">" +
                        "<img class=\"img-fluid\" src=\"" + imageUrl + "\" alt=\"" + cat.name + "\" loading=\"lazy\">" +
                    "</a>" +
                "</div>" +
                "<h5>" +
                    "<a class=\"subcategory-name\" href=\"" + categoryLink + "\">" + cat.name + "</a>" +
                "</h5>" +
            "</li>";
            
            $list.insertAdjacentHTML("beforeend", html);
        } else {
            console.log('DEBUG: Category already exists: ' + cat.name);
        }
    });
});
