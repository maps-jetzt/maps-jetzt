import { Controller } from '@hotwired/stimulus';
import { fromLonLat } from 'ol/proj';
import { View } from 'ol';
import { Group, VectorTile as VectorTileLayer } from 'ol/layer';
import { VectorTile as VectorTileSource } from 'ol/source';
import { Stroke, Style } from 'ol/style';
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
            source: new VectorTileSource({
                format: new MVT(),
                url: `/tiles/heatmaps/${identifier}/{z}/{x}/{y}.mvt`,
            }),
            style: new Style({
                stroke: new Stroke({
                    color: 'rgba(255, 50, 0, 0.15)',
                    width: 3,
                }),
            }),
        });

        vectorTileLayer.on('prerender', function (evt) {
            evt.context.globalCompositeOperation = 'lighter';
        });
        vectorTileLayer.on('postrender', function (evt) {
            evt.context.globalCompositeOperation = 'source-over';
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
