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
    };

    connect() {
        const identifier = this.identifierValue;

        const vectorTileLayer = new VectorTileLayer({
            source: new VectorTileSource({
                format: new MVT(),
                url: `/tiles/heatmaps/${identifier}/{z}/{x}/{y}.mvt`,
            }),
            style: function () {
                return new Style({
                    stroke: new Stroke({
                        color: 'rgba(255, 0, 0, 1)',
                        width: 2,
                    }),
                });
            },
        });

        const baseLayer = new Group();

        const map = new Map({
            target: this.element,
            layers: [baseLayer, vectorTileLayer],
            view: new View({
                center: fromLonLat([10, 53]),
                zoom: 12,
            }),
        });

        apply(baseLayer, 'https://tiles.openfreemap.org/styles/liberty');
    }
}
