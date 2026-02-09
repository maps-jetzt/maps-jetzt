import { Controller } from '@hotwired/stimulus';
import { fromLonLat } from 'ol/proj';
import { View } from 'ol';
import { Tile, VectorTile as VectorTileLayer } from 'ol/layer';
import { OSM, VectorTile as VectorTileSource } from 'ol/source';
import { Stroke, Style } from 'ol/style';
import { MVT } from 'ol/format';
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

        const osmLayer = new Tile({
            source: new OSM(),
        });

        const map = new Map({
            target: this.element,
            layers: [osmLayer, vectorTileLayer],
            view: new View({
                center: fromLonLat([10, 53]),
                zoom: 12,
            }),
        });
    }
}
