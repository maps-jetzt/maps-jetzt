import { Controller } from '@hotwired/stimulus';
import { fromLonLat } from 'ol/proj';
import { View } from 'ol';
import { Group, VectorTile as VectorTileLayer } from 'ol/layer';
import { VectorTile as VectorTileSource } from 'ol/source';
import { Style } from 'ol/style';
import { MVT } from 'ol/format';
import { apply } from 'ol-mapbox-style';
import Map from 'ol/Map';

export default class extends Controller {
    static values = {
        identifier: String,
        centerLon: { type: Number, default: 10 },
        centerLat: { type: Number, default: 53 },
        zoom: { type: Number, default: 12 },
    };

    connect() {
        const identifier = this.identifierValue;

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
                    ctx.strokeStyle = 'rgba(255, 50, 0, 0.15)';
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

        const baseLayer = new Group();

        const map = new Map({
            target: this.element,
            layers: [baseLayer, vectorTileLayer],
            view: new View({
                center: fromLonLat([this.centerLonValue, this.centerLatValue]),
                zoom: this.zoomValue,
            }),
        });

        apply(baseLayer, 'https://tiles.openfreemap.org/styles/liberty');
    }
}
