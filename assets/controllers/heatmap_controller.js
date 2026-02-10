import { Controller } from '@hotwired/stimulus';
import { fromLonLat } from 'ol/proj';
import { View } from 'ol';
import { Group, VectorTile as VectorTileLayer } from 'ol/layer';
import { VectorTile as VectorTileSource } from 'ol/source';
import { Style } from 'ol/style';
import { MVT } from 'ol/format';
import { apply } from 'ol-mapbox-style';
import Map from 'ol/Map';
import Overlay from 'ol/Overlay';
import libertyStyle from '../styles/liberty.json';

const COLOR_SCHEMES = {
    fire:   'rgba(255, 50, 0, 0.15)',
    blue:   'rgba(0, 100, 255, 0.15)',
    purple: 'rgba(150, 0, 255, 0.15)',
    green:  'rgba(0, 200, 50, 0.15)',
};

const SELECTED_COLORS = {
    fire:   'rgba(255, 80, 30, 0.7)',
    blue:   'rgba(50, 140, 255, 0.7)',
    purple: 'rgba(180, 50, 255, 0.7)',
    green:  'rgba(50, 230, 80, 0.7)',
};

function renderLines(coordinates, ctx) {
    const lines = Array.isArray(coordinates[0][0])
        ? coordinates
        : [coordinates];

    for (const line of lines) {
        ctx.moveTo(line[0][0], line[0][1]);
        for (let i = 1; i < line.length; i++) {
            ctx.lineTo(line[i][0], line[i][1]);
        }
    }
}

export default class extends Controller {
    static values = {
        identifier: String,
        centerLon: { type: Number, default: 10 },
        centerLat: { type: Number, default: 53 },
        zoom: { type: Number, default: 12 },
    };

    connect() {
        const identifier = this.identifierValue;
        let currentScheme = 'fire';
        let currentColor = COLOR_SCHEMES.fire;
        let selectedColor = SELECTED_COLORS.fire;
        let selectedId = null;

        const tileSource = new VectorTileSource({
            format: new MVT(),
            url: `/tiles/heatmaps/${identifier}/{z}/{x}/{y}.mvt`,
            maxZoom: 14,
        });

        const vectorTileLayer = new VectorTileLayer({
            className: 'heatmap-layer',
            source: tileSource,
            style: new Style({
                renderer(coordinates, state) {
                    const ctx = state.context;
                    const featureId = state.feature.getId();
                    const isSelected = selectedId !== null && featureId === selectedId;

                    ctx.save();
                    ctx.lineJoin = 'round';
                    ctx.lineCap = 'round';
                    ctx.beginPath();
                    renderLines(coordinates, ctx);

                    if (isSelected) {
                        ctx.globalCompositeOperation = 'source-over';
                        ctx.strokeStyle = selectedColor;
                        ctx.lineWidth = 5;
                    } else {
                        ctx.globalCompositeOperation = 'lighter';
                        ctx.strokeStyle = currentColor;
                        ctx.lineWidth = 3;
                    }

                    ctx.stroke();
                    ctx.restore();
                },
                hitDetectionRenderer(coordinates, state) {
                    const ctx = state.context;
                    ctx.lineJoin = 'round';
                    ctx.lineCap = 'round';
                    ctx.lineWidth = 10;
                    ctx.beginPath();
                    renderLines(coordinates, ctx);
                    ctx.stroke();
                },
            }),
        });

        const select = document.getElementById('color-scheme');
        if (select) {
            select.addEventListener('change', () => {
                currentScheme = select.value;
                currentColor = COLOR_SCHEMES[currentScheme];
                selectedColor = SELECTED_COLORS[currentScheme];
                vectorTileLayer.changed();
            });
        }

        // Popup overlay
        const popupEl = document.createElement('div');
        popupEl.className = 'heatmap-popup';
        document.body.appendChild(popupEl);

        const overlay = new Overlay({
            element: popupEl,
            positioning: 'bottom-center',
            offset: [0, -10],
            stopEvent: true,
        });

        const baseLayer = new Group();

        const map = new Map({
            target: this.element,
            layers: [baseLayer, vectorTileLayer],
            view: new View({
                center: fromLonLat([this.centerLonValue, this.centerLatValue]),
                zoom: this.zoomValue,
            }),
            overlays: [overlay],
        });

        apply(baseLayer, libertyStyle);

        map.on('click', (evt) => {
            let hit = null;

            map.forEachFeatureAtPixel(evt.pixel, (feature) => {
                hit = feature;
                return true; // stop after first hit
            }, { layerFilter: (l) => l === vectorTileLayer });

            if (hit) {
                const hash = hit.get('hash');
                selectedId = hit.getId();
                vectorTileLayer.changed();

                const shortHash = hash ? hash.substring(0, 8) : '?';
                popupEl.innerHTML = `
                    <div class="heatmap-popup-content">
                        <div class="heatmap-popup-hash"><code>${shortHash}</code></div>
                        <button class="heatmap-popup-delete" type="button">Löschen</button>
                    </div>
                `;

                overlay.setPosition(evt.coordinate);

                popupEl.querySelector('.heatmap-popup-delete').addEventListener('click', () => {
                    if (!hash) return;
                    fetch(`/api/heatmaps/${identifier}/polylines/${hash}`, {
                        method: 'DELETE',
                    }).then((res) => {
                        if (res.ok) {
                            selectedId = null;
                            overlay.setPosition(undefined);
                            tileSource.refresh();
                        }
                    });
                });
            } else {
                selectedId = null;
                overlay.setPosition(undefined);
                vectorTileLayer.changed();
            }
        });

        // Change cursor on hover
        map.on('pointermove', (evt) => {
            if (evt.dragging) return;
            let hit = false;
            map.forEachFeatureAtPixel(evt.pixel, () => {
                hit = true;
                return true;
            }, { layerFilter: (l) => l === vectorTileLayer });
            map.getTargetElement().style.cursor = hit ? 'pointer' : '';
        });
    }
}
