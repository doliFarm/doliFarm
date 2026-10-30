<?php
/**
 * Library for DoliFarm Maps & GeoJSON handling
 * File: custom/dolifarm/lib/dolifarm_map.lib.php
 * * STRATEGIA ROBUSTA:
 * - Proxy: Punta alla card stessa (?action=wms_proxy)
 * - WMS: Versione 1.1.1 (Evita inversione assi X/Y)
 * - CRS: EPSG:3857 (Standard Google/Leaflet)
 */

/**
 * Print the map column of a plot card (right half of the page).
 * The map stays visible while the fields on the left are scrolled.
 *
 * @param string $mode 'edit' (create and edit forms) or 'view'
 * @return void
 */
function dolifarm_print_map_container($mode = 'edit')
{
    global $langs;

    print '<div class="fichehalfright">';
    print '<div style="position: sticky; top: 60px;">';
    print '<div id="dolifarm_map_'.($mode == 'view' ? 'view' : 'edit').'" style="width: 100%; height: calc(100vh - 220px); min-height: 400px; max-height: 600px; border: 1px solid #bbb; border-radius: 4px; margin-bottom: 10px;"></div>';
    if ($mode != 'view') {
        print '<div class="opacitymedium"><span class="fa fa-info-circle"></span> '.$langs->trans("DrawPolygonToCalculateArea").'</div>';
    }
    print '</div>';
    print '</div>';
}

function dolifarm_print_map_script($mode, $lat, $lon, $geojson_content = '', $target_input_id = '') {
    
    $is_editable_js = ($mode == 'edit') ? 'true' : 'false';
    $map_id = "dolifarm_map_" . $mode;
    
    // --- STRATEGIA: PROXY INTEGRATO ---
    // Puntiamo alla card stessa. Aggiungiamo un token fittizio per evitare cache
    $proxy_url = dol_buildpath('/dolifarm/dolifarmplot_card.php', 1) . '?action=wms_proxy';
    
    // Lookup of the cadastral parcel at a point (public WMS of the Agenzia delle Entrate)
    global $langs;
    $langs->load('dolifarm@dolifarm');
    $cadastral_url = dol_buildpath('/dolifarm/ajax/cadastralinfo.php', 1);
    $js_trans = json_encode(array(
        'title' => $langs->transnoentitiesnoconv('CadastralParcelFound'),
        'searching' => $langs->transnoentitiesnoconv('CadastralSearching'),
        'comune' => $langs->transnoentitiesnoconv('Cadastralcomune'),
        'section' => $langs->transnoentitiesnoconv('CadastralSection'),
        'sheet' => $langs->transnoentitiesnoconv('Cadastralsheet'),
        'parcel' => $langs->transnoentitiesnoconv('Cadastralparcel'),
        'fill' => $langs->transnoentitiesnoconv('CadastralFillFields'),
        'CadastralParcelNotFound' => $langs->transnoentitiesnoconv('CadastralParcelNotFound'),
        'CadastralPointOutsideItaly' => $langs->transnoentitiesnoconv('CadastralPointOutsideItaly'),
        'CadastralServiceUnavailable' => $langs->transnoentitiesnoconv('CadastralServiceUnavailable'),
        'NotEnoughPermissions' => $langs->transnoentitiesnoconv('NotEnoughPermissions'),
    ));

    $lat_safe = dol_escape_js($lat);
    $lon_safe = dol_escape_js($lon);
    // JS string literal (dol_escape_js would turn the double quotes of the JSON into single quotes)
    $geojson_js = json_encode((string) $geojson_content, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

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
                var cadastralUrl = "{$cadastral_url}";
                var cadastralTrans = {$js_trans};
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
                
                var savedGeoJson = {$geojson_js};
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
                        var c = (e.layer.getBounds) ? e.layer.getBounds().getCenter() : e.layer.getLatLng();
                        lookupParcel(c);
                    });
                    map.on(L.Draw.Event.EDITED, function (e) {
                        e.layers.eachLayer(function (l) { updateFormFromMap(l); });
                    });
                    map.on(L.Draw.Event.DELETED, function(e) { $("#shape_json").val(""); });
                }

                var drawingActive = false;
                map.on("draw:drawstart draw:editstart draw:deletestart", function() { drawingActive = true; });
                map.on("draw:drawstop draw:editstop draw:deletestop", function() { setTimeout(function() { drawingActive = false; }, 300); });
                map.on("click", function(e) {
                    if (!drawingActive) lookupParcel(e.latlng);
                });

                // Button next to the cadastral fields: parcel at the centre of the drawn shape, else of the map
                $(document).on("click", ".dolifarm-cadastral-lookup", function(ev) {
                    ev.preventDefault();
                    var c = map.getCenter();
                    drawnItems.eachLayer(function(l) { c = (l.getBounds) ? l.getBounds().getCenter() : l.getLatLng(); });
                    lookupParcel(c);
                });

                // Ask the cadastral parcel at a point and show it in a popup
                function lookupParcel(latlng) {
                    var popup = L.popup().setLatLng(latlng).setContent(cadastralTrans.searching).openOn(map);
                    $.getJSON(cadastralUrl, { lat: latlng.lat.toFixed(7), lon: latlng.lng.toFixed(7) }, function(data) {
                        if (!data || !data.success) {
                            popup.setContent(cadastralTrans[(data && data.error) ? data.error : "CadastralServiceUnavailable"] || cadastralTrans.CadastralServiceUnavailable);
                            return;
                        }
                        var html = "<b>" + cadastralTrans.title + "</b><br>" + $("<span>").text(data.reference).html();
                        // Comune as in the AGEA dossier (name in capitals), the code when the name is not in the dictionary
                        var comune = data.comune_name ? data.comune_name.toUpperCase() : data.comune_code;
                        var comuneLabel = data.comune_name ? data.comune_name + (data.province ? " (" + data.province + ")" : "") + " - " + data.comune_code : data.comune_code;
                        if (data.sheet) {
                            html += "<br>" + cadastralTrans.comune + ": " + $("<span>").text(comuneLabel).html()
                                + (data.section ? " - " + cadastralTrans.section + ": " + $("<span>").text(data.section).html() : "")
                                + "<br>" + cadastralTrans.sheet + ": " + $("<span>").text(data.sheet).html()
                                + " - " + cadastralTrans.parcel + ": " + $("<span>").text(data.parcel).html();
                        }
                        if (isEditable) {
                            html += "<br><br><a href=\"#\" class=\"button small dolifarm-fill-cadastral\">" + cadastralTrans.fill + "</a>";
                        }
                        popup.setContent(html);
                        $(".dolifarm-fill-cadastral").off("click").on("click", function(ev) {
                            ev.preventDefault();
                            $("input[name='cadastral_reference']").val(data.reference);
                            if (data.sheet) {
                                $("input[name='cadastral_comune']").val(comune);
                                $("input[name='cadastral_section']").val(data.section);
                                $("input[name='cadastral_sheet']").val(data.sheet);
                                $("input[name='cadastral_parcel']").val(data.parcel);
                            }
                            map.closePopup();
                        });
                    }).fail(function() {
                        popup.setContent(cadastralTrans.CadastralServiceUnavailable);
                    });
                }

                function updateFormFromMap(layer) {
                    if (!isEditable) return;
                    $("#shape_json").val(JSON.stringify(layer.toGeoJSON()));

                    var center = (layer.getBounds) ? layer.getBounds().getCenter() : layer.getLatLng();
                    if (center) {
                        var latIn = $("input[name='geo_lat']");
                        var lonIn = $("input[name='geo_lon']");
                        if(latIn.length) latIn.val(center.lat.toFixed(6));
                        if(lonIn.length) lonIn.val(center.lng.toFixed(6));
                    }

                    if (layer instanceof L.Polygon || layer instanceof L.Rectangle) {
                        var areaHa = (L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]) / 10000).toFixed(4);
                        var areaField = $("input[name='size_total']");
                        if(areaField.length === 0) areaField = $("input[name='surface']");
                        
                        if(areaField.length) {
                            areaField.val(areaHa);
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