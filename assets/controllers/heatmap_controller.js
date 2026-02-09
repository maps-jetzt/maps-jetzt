import { Controller } from '@hotwired/stimulus';
import { fromLonLat } from 'ol/proj';
import { View } from 'ol';
import { Group, VectorTile as VectorTileLayer } from 'ol/layer';
import { VectorTile as VectorTileSource } from 'ol/source';
import { Style } from 'ol/style';
import { MVT } from 'ol/format';
import { apply } from 'ol-mapbox-style';
import Map from 'ol/Map';
import Control from 'ol/control/Control';

const COLOR_SCHEMES = {
    fire:   'rgba(255, 50, 0, 0.15)',
    blue:   'rgba(0, 100, 255, 0.15)',
    purple: 'rgba(150, 0, 255, 0.15)',
    green:  'rgba(0, 200, 50, 0.15)',
};

export default class extends Controller {
    static values = {
        identifier: String,
        centerLon: { type: Number, default: 10 },
        centerLat: { type: Number, default: 53 },
        zoom: { type: Number, default: 12 },
    };

    connect() {
        const identifier = this.identifierValue;
        let currentColor = COLOR_SCHEMES.fire;

        const vectorTileLayer = new VectorTileLayer({
            className: 'heatmap-layer',
            renderMode: 'vector',
            source: new VectorTileSource({
                format: new MVT(),
                url: `/tiles/heatmaps/${identifier}/{z}/{x}/{y}.mvt`,
            }),
            style: new Style({
                renderer(coordinates, state) {
                    const ctx = state.context;
                    ctx.globalCompositeOperation = 'lighter';
                    ctx.strokeStyle = currentColor;
                    ctx.lineWidth = 3;
                    ctx.lineJoin = 'round';
                    ctx.lineCap = 'round';
                    ctx.beginPath();
                    ctx.moveTo(coordinates[0][0], coordinates[0][1]);
                    for (let i = 1; i < coordinates.length; i++) {
                        ctx.lineTo(coordinates[i][0], coordinates[i][1]);
                    }
                    ctx.stroke();
                },
            }),
        });

        // Color scheme dropdown control
        const select = document.createElement('select');
        for (const [name, color] of Object.entries(COLOR_SCHEMES)) {
            const option = document.createElement('option');
            option.value = color;
            option.textContent = name.charAt(0).toUpperCase() + name.slice(1);
            select.appendChild(option);
        }
        select.addEventListener('change', () => {
            currentColor = select.value;
            vectorTileLayer.changed();
        });

        const controlElement = document.createElement('div');
        controlElement.className = 'color-scheme-control ol-unselectable ol-control';
        controlElement.appendChild(select);

        const baseLayer = new Group();

        const map = new Map({
            target: this.element,
            layers: [baseLayer, vectorTileLayer],
            view: new View({
                center: fromLonLat([this.centerLonValue, this.centerLatValue]),
                zoom: this.zoomValue,
            }),
        });

        map.addControl(new Control({ element: controlElement }));

        apply(baseLayer, 'https://tiles.openfreemap.org/styles/liberty');
    }
}
