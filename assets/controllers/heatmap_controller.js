import { Controller } from '@hotwired/stimulus';
import { fromLonLat } from 'ol/proj';
import { View } from 'ol';
import { Group, VectorTile as VectorTileLayer } from 'ol/layer';
import { VectorTile as VectorTileSource } from 'ol/source';
import { Style } from 'ol/style';
import { MVT } from 'ol/format';
import { apply } from 'ol-mapbox-style';
import Map from 'ol/Map';
import libertyStyle from '../styles/liberty.json';

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
            source: new VectorTileSource({
                format: new MVT(),
                url: `/tiles/heatmaps/${identifier}/{z}/{x}/{y}.mvt`,
                maxZoom: 14,
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

        const select = document.getElementById('color-scheme');
        if (select) {
            select.addEventListener('change', () => {
                currentColor = COLOR_SCHEMES[select.value];
                vectorTileLayer.changed();
            });
        }

        const baseLayer = new Group();

        const map = new Map({
            target: this.element,
            layers: [baseLayer, vectorTileLayer],
            view: new View({
                center: fromLonLat([this.centerLonValue, this.centerLatValue]),
                zoom: this.zoomValue,
            }),
        });

        apply(baseLayer, libertyStyle);
    }
}
