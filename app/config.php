<?php
// Tree Huggers configuration. Copy any key into app/config.local.php to override it.
return [
    'site_name'   => 'Tree Huggers',
    'tagline'     => 'a map of trees people love',
    // Google Analytics: same property and shared analytics.js as the rest of slvtrs.com
    // (it honors the internal_traffic cookie). Set to null to disable.
    'ga_id'       => 'G-WKWPJB3H3B',
    'ga_script'   => 'https://slvtrs.com/analytics.js',

    'debug'       => true, // TEMPORARY: show errors while we get the first deploy working
    'base_path'   => null,  // e.g. '/tree_huggers'; null = detect automatically

    // Storage. By default data/ lives beside app/, outside the web root.
    'data_dir'    => dirname(__DIR__) . '/data',
    'uploads_dir' => PUBLIC_DIR . '/uploads',
    'uploads_url' => '/uploads',

    // Map
    'tile_url'         => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    'tile_attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    'default_center'   => [39.5, -98.0],
    'default_zoom'     => 4,
    'pixel_size'       => 4,

    // Photos
    'max_upload_bytes' => 10 * 1024 * 1024,
    'photo_max_px'     => 1600,
    'thumb_px'         => 240,

    // Species data (iNaturalist public API, no key needed)
    'inat_api'      => 'https://api.inaturalist.org/v1',
    'cache_ttl'     => 86400,
    'nearby_radius_km' => 30,
    // iNaturalist taxon ids used to filter "trees near you" recommendations:
    // families that are (nearly) all trees, plus tree genera from mixed families
    // such as Rosaceae and Fabaceae, so clover and poison ivy stay out.
    'tree_taxa' => [
        // families
        71434, // Altingiaceae
        53855, // Aquifoliaceae
        49382, // Araucariaceae
        48867, // Arecaceae
        49155, // Betulaceae
        48377, // Bignoniaceae
        71475, // Cercidiphyllaceae
        47194, // Cornaceae
        47374, // Cupressaceae
        71511, // Eucommiaceae
        47852, // Fagaceae
        64354, // Ginkgoaceae
        49660, // Hamamelidaceae
        54497, // Juglandaceae
        48809, // Lauraceae
        53581, // Magnoliaceae
        53724, // Meliaceae
        50998, // Moraceae
        51816, // Myrtaceae
        532720, // Nyssaceae
        71586, // Paulowniaceae
        47562, // Pinaceae
        49663, // Platanaceae
        70279, // Podocarpaceae
        47567, // Salicaceae
        58321, // Sapindaceae
        57279, // Simaroubaceae
        61874, // Styracaceae
        47556, // Taxaceae
        71645, // Theaceae
        53548, // Ulmaceae
        // genera
        47452, // Acacia
        81507, // Adansonia
        47451, // Albizia
        49230, // Amelanchier
        122989, // Anacardium
        51047, // Arbutus
        68226, // Bauhinia
        68314, // Bombax
        122303, // Brachychiton
        1065446, // Caesalpinia
        68685, // Cassia
        202156, // Castanospermum
        62819, // Ceiba
        54858, // Celtis
        82743, // Ceratonia
        48801, // Cercis
        54655, // Chionanthus
        54297, // Citrus
        117431, // Cladrastis
        133368, // Cotinus
        51148, // Crataegus
        82855, // Cydonia
        62852, // Delonix
        52441, // Enterolobium
        72119, // Eriobotrya
        82771, // Erythrina
        157348, // Firmiana
        54806, // Fraxinus
        48498, // Gleditsia
        54799, // Gymnocladus
        48871, // Inga
        51811, // Laburnum
        68717, // Lagerstroemia
        69819, // Ligustrum
        133365, // Maackia
        54500, // Malus
        48875, // Mangifera
        62820, // Ochroma
        57145, // Olea
        156674, // Osmanthus
        133355, // Oxydendrum
        154862, // Pachira
        62834, // Parkinsonia
        133611, // Peltophorum
        156609, // Phellodendron
        72301, // Pistacia
        48060, // Prosopis
        47351, // Prunus
        58302, // Punica
        58736, // Pyrus
        54765, // Rhus
        56089, // Robinia
        138599, // Samanea
        57355, // Schinus
        72356, // Senegalia
        70037, // Sophora
        48582, // Sorbus
        85036, // Spondias
        53946, // Styphnolobium
        62855, // Tamarindus
        332552, // Tetradium
        64341, // Theobroma
        54856, // Tilia
        121264, // Tipuana
        72418, // Vachellia
        54837, // Zanthoxylum
    ],
];
