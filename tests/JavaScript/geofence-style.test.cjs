const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const style = require('../../public/js/geofence-style.js');

test('both map representations retain saved white, zero, solid and legacy default opacity', () => {
  for (const opacity of [0, .2, .67, 1]) {
    const boundary = {color:'#ffffff', fill_opacity:opacity};
    assert.deepEqual(style.normalize(boundary), {color:'#FFFFFF', fill_opacity:opacity});
    assert.equal(style.rgba(boundary), `rgba(255,255,255,${opacity})`);
  }
  assert.equal(style.normalize({}).fill_opacity, .2);
  assert.equal(style.normalize({fill_opacity:null}).fill_opacity, .2);
  assert.equal(style.normalize({color:'url(bad)', fill_opacity:NaN}).color, '#15803D');
});

test('both maps consume only valid server-calculated label positions', () => {
  const point = {lat:15.4,lng:121.6};
  assert.deepEqual(style.labelPosition({label_position:point}), point);
  assert.equal(style.labelPosition({label_position:null}), null);
  assert.equal(style.labelPosition({label_position:{lat:NaN,lng:121.6}}), null);
});

test('parcel renderer preserves holes and saved appearance and hides municipality labels with the layer', () => {
  const source = fs.readFileSync('public/js/farmers-maps.js','utf8');
  const start = source.indexOf('    function geoJsonPolygons('), end = source.indexOf('    var plotsLoadingEnabled',start);
  const shapes = [], markers = [], listeners = {};
  const map = {range:40000, append(node){node.isConnected=true;}, removeChild(node){node.isConnected=false;}, addEventListener(name, fn){listeners[name]=fn;}};
  const toggle = {checked:true};
  const ring = [[120,15],[121,15],[121,16],[120,16],[120,15]], hole = [[120.3,15.3],[120.4,15.3],[120.4,15.4],[120.3,15.4],[120.3,15.3]];
  const data = {municipality_id:1,municipality_name:'Town',color:'#ffffff',fill_opacity:.67,label_position:{lat:15.5,lng:120.5},geojson:{type:'Polygon',coordinates:[ring,hole]}};
  const context = vm.createContext({window:{GeofenceStyle:{...style,labelTemplate:()=>({})},__municipalityGeofenceData:[data]},
    document:{getElementById:()=>toggle}, map3d:map, municipalityGeofenceOverlays:[],municipalityGeofenceLabels:[],
    AltitudeMode:{CLAMP_TO_GROUND:'ground'}, google:{maps:{CollisionBehavior:{OPTIONAL_AND_HIDES_LOWER_PRIORITY:'optional'}}},
    openRing:points=>points.slice(0,-1), setOverlayVisible:(node,visible)=>{node.isConnected=visible;},
    Polygon3DElement:class {constructor(options){Object.assign(this,options);shapes.push(this);}},
    Marker3DElement:class {constructor(options){Object.assign(this,options);markers.push(this);}replaceChildren(){}},
  });
  vm.runInContext(source.slice(start,end),context); context.renderMunicipalityGeofences();
  assert.equal(shapes[0].fillColor,'rgba(255,255,255,0.67)'); assert.equal(shapes[0].strokeColor,'#FFFFFF');
  assert.equal(shapes[0].innerPaths.length,1); assert.equal(markers.length,1); assert.equal(markers[0].isConnected,true);
  map.range=100000; listeners['gmp-rangechange'](); assert.equal(markers[0].isConnected,false);
  map.range=40000; toggle.checked=false; context.window.__applyMunicipalityGeofenceVisibility();
  assert.equal(shapes[0].isConnected,false); assert.equal(markers[0].isConnected,false);
});
