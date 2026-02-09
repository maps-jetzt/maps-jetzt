import { Controller } from '@hotwired/stimulus';
import {fromLonLat} from "ol/proj";
import {View} from "ol";
import {Tile, VectorTile as VectorTileLayer} from "ol/layer";
import {OSM, VectorTile as VectorTileSource} from "ol/source";
import {Fill, Stroke, Style} from "ol/style";
import {MVT} from "ol/format";
import Map from 'ol/Map';

/*
 * This is an example Stimulus controller!
 *
 * Any element with a data-controller="hello" attribute will cause
 * this controller to be executed. The name "hello" comes from the filename:
 * hello_controller.js -> "hello"
 *
 * Delete this file or adapt it for your use!
 */
export default class extends Controller {
    connect() {
        const vectorTileLayer = new VectorTileLayer({
            source: new VectorTileSource({
                format: new MVT(),
                url: '/tiles/{z}/{x}/{y}.mvt'
            }),
            style: new Style({
                stroke: new Stroke({
                    color: 'rgba(255, 0, 0, 0.8)',
                    width: 2
                })
            })
        });

        const osmLayer = new Tile({
            source: new OSM()
        });

        const map = new Map({
            target: 'map',
            layers: [
                osmLayer,
                vectorTileLayer,
            ],
            view: new View({
                center: fromLonLat([10, 50]),
                zoom: 6
            })
        });
    }
}
