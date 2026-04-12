<?php
/**
 * Library for DoliFarm Maps & GeoJSON handling
 * File: custom/dolifarm/lib/dolifarm_map.lib.php
 * * STRATEGIA ROBUSTA:
 * - Proxy: Punta alla card stessa (?action=wms_proxy)
 * - WMS: Versione 1.1.1 (Evita inversione assi X/Y)
 * - CRS: EPSG:3857 (Standard Google/Leaflet)
 */

function dolifarm_print_map_script($mode, $lat, $lon, $geojson_content = '', $target_input_id = '') {
    
    $is_editable_js = ($mode == 'edit') ? 'true' : 'false';
    $map_id = "dolifarm_map_" . $mode;
    
    // --- STRATEGIA: PROXY INTEGRATO ---
    // Puntiamo alla card stessa. Aggiungiamo un token fittizio per evitare cache
    $proxy_url = dol_buildpath('/dolifarm/dolifarmplot_card.php', 1) . '?action=wms_proxy';
    
    $lat_safe = dol_escape_js($lat);
    $lon_safe = dol_escape_js($lon);
    $geojson_safe = dol_escape_js($geojson_content);

    // Caricamento Assets
    print '';
    print '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>';
    print '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>';
    print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>';
    print '<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>';
    print '<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-geometryutil/0.9.3/leaflet.geometryutil.min.js"></script>';

    if ($mode == 'edit') {
        $safe_geojson_html = dol_escape_htmltag($geojson_content);
        print '<input type="hidden" name="shape_json" id="shape_json" value="'.$safe_geojson_html.'">';
    }

    print <<<SCRIPT
    <script type="text/javascript">
        $(document).ready(function() {
            try {
                var isEditable = {$is_editable_js};
                var mapId = "{$map_id}";
                var mapContainer = document.getElementById(mapId);
                
                if (!mapContainer) return;

                // 1. LAYER BASE
                var osmLayer = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 21, attribution: "OSM" });
                var satLayer = L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", { maxZoom: 21, attribution: "Esri" });
                var googleHybrid = L.tileLayer("https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}", { maxZoom: 21, attribution: "Google" });

                // 2. CATASTO (Configurazione WMS 1.1.1 "Blindata")
                var catastoLayer = L.tileLayer.wms("{$proxy_url}", {
                    layers: "CP.CadastralZoning,CP.CadastralParcel", // Fogli e Particelle
                    format: "image/png",
                    transparent: true,
                    version: "1.1.1",        // <--- 1.1.1 è la chiave per la stabilità
                    crs: L.CRS.EPSG3857,     // Web Mercator
                    attribution: "Agenzia Entrate",
                    minZoom: 15,             // Sotto questo zoom l'Agenzia manda immagine bianca
                    maxZoom: 21,
                    uppercase: true,
                    tiled: true
                });

                // 3. INIT MAPPA
                var currentLat = "{$lat_safe}";
                var currentLon = "{$lon_safe}";
                // Default: Centro di Roma (zona agricola) se non ci sono coordinate
                var startLat = (currentLat && currentLat !== "") ? parseFloat(currentLat) : 41.85; 
                var startLon = (currentLon && currentLon !== "") ? parseFloat(currentLon) : 12.55;
                var zoomLevel = (currentLat && currentLat !== "") ? 18 : 12; 

                var map = L.map(mapId, {
                    center: [startLat, startLon],
                    zoom: zoomLevel,
                    layers: [satLayer] // Start with Satellite
                });

                L.control.layers(
                    { "Satellite (Esri)": satLayer, "Google": googleHybrid, "Stradale": osmLayer },
                    { "<span style='color:red; font-weight:bold;'>🏛️ Catasto</span>": catastoLayer }
                ).addTo(map);

                // Auto-attivazione Catasto se siamo vicini
                if(zoomLevel >= 16) map.addLayer(catastoLayer);

                // 4. DRAWING & DATA
                var drawnItems = new L.FeatureGroup();
                map.addLayer(drawnItems);
                
                var savedGeoJson = "{$geojson_safe}";
                if (savedGeoJson && savedGeoJson !== "") {
                    try {
                        var parsed = JSON.parse(savedGeoJson);
                        L.geoJson(parsed, {
                            onEachFeature: function (f, l) {
                                drawnItems.addLayer(l);
                                if(l.getBounds) map.fitBounds(l.getBounds());
                            }
                        });
                    } catch(e) {}
                }

                if (isEditable) {
                    var drawControl = new L.Control.Draw({
                        edit: { featureGroup: drawnItems, remove: true },
                        draw: {
                            polygon: { allowIntersection: false, showArea: true, shapeOptions: { color: "#e67e22", weight: 3 } },
                            rectangle: true, marker: true, circle: false, polyline: false, circlemarker: false
                        }
                    });
                    map.addControl(drawControl);

                    map.on(L.Draw.Event.CREATED, function (e) {
                        drawnItems.clearLayers(); drawnItems.addLayer(e.layer); updateFormFromMap(e.layer);
                    });
                    map.on(L.Draw.Event.EDITED, function (e) {
                        e.layers.eachLayer(function (l) { updateFormFromMap(l); });
                    });
                    map.on(L.Draw.Event.DELETED, function(e) { $("#shape_json").val(""); });
                }

                function updateFormFromMap(layer) {
                    if (!isEditable) return;
                    $("#shape_json").val(JSON.stringify(layer.toGeoJSON()));

                    var center = (layer.getBounds) ? layer.getBounds().getCenter() : layer.getLatLng();
                    if (center) {
                        var latIn = $("input[name='geolat']").length ? $("input[name='geolat']") : $("input[name='options_geolat']");
                        var lonIn = $("input[name='geolon']").length ? $("input[name='geolon']") : $("input[name='options_geolon']");
                        if(latIn.length) latIn.val(center.lat.toFixed(6));
                        if(lonIn.length) lonIn.val(center.lng.toFixed(6));
                    }

                    if (layer instanceof L.Polygon || layer instanceof L.Rectangle) {
                        var areaHa = (L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]) / 10000).toFixed(4);
                        var areaField = $("input[name='size_total']");
                        if(areaField.length === 0) areaField = $("input[name='surface']");
                        
                        if(areaField.length) {
                            areaField.val(areaHectares);
                            areaField.css("background-color", "#e8f5e9");
                        }
                    }
                }
                
                setTimeout(function(){ map.invalidateSize()}, 500);

            } catch(e) { console.error("Map Error: " + e); }
        });
    </script>
SCRIPT;
}
?>