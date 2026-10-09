const test=require('node:test');
const assert=require('node:assert/strict');
const guide=require('../../public/js/map-drawing-guide.js');
function adapter(){
  const markers=[],lines=[],elements=[];
  class Element{constructor(name){this.name=name;this.attrs={};this.children=[];this.style={};elements.push(this);}setAttribute(k,v){this.attrs[k]=String(v);}appendChild(v){this.children.push(v);}remove(){this.removed=true;}}
  const document={createElement:name=>{const el=new Element(name);if(name==='template')el.content=new Element('fragment');return el;},createElementNS:(_,name)=>new Element(name)};
  class Polyline{constructor(options){this.path=options.path;lines.push(this);}setPath(points){this.path=points;}setMap(map){this.map=map;}}
  class OverlayView{constructor(){markers.push(this);}getPanes(){return {overlayLayer:{appendChild(){}}};}getProjection(){return {fromLatLngToDivPixel:p=>({x:p.lng*10,y:p.lat*10})};}setMap(map){this.map=map;if(map){this.onAdd();this.draw();}else this.onRemove();}}
  class LatLng{constructor(lat,lng){this.lat=lat;this.lng=lng;}}
  const map={};
  return {drawing:guide.create({Polyline,OverlayView,LatLng},document,map),map,markers,lines,elements,document};
}
test('first corner has a visible green numbered dot, second corner connects an open line',()=>{
  const a=adapter();a.drawing.update([{lat:1,lng:2}]);
  assert.equal(a.markers.length,1);assert.equal(a.markers[0].map,a.map);assert.equal(a.lines[0].map,null);
  assert.ok(a.elements.some(el=>el.attrs.fill==='#236344'));
  assert.ok(a.elements.some(el=>el.name==='text'&&el.textContent==='1'));
  a.drawing.update([{lat:1,lng:2},{lat:2,lng:3}]);assert.equal(a.markers.length,2);assert.equal(a.lines[0].map,a.map);assert.equal(a.lines[0].path.length,2);
  assert.ok(a.elements.some(el=>el.name==='text'&&el.textContent==='2'));
});
test('dot positions reuse overlays; undo and cancel remove both dots and line',()=>{
  const a=adapter();a.drawing.update([{lat:1,lng:2},{lat:2,lng:3}]);
  a.drawing.update([{lat:3,lng:4},{lat:4,lng:5}]);assert.equal(a.markers.length,2);assert.equal(a.markers[0].position.lng,4);
  a.drawing.update([{lat:3,lng:4}]);assert.equal(a.markers[1].map,null);assert.equal(a.lines[0].map,null);
  a.drawing.remove();assert.equal(a.markers[0].map,null);assert.equal(a.lines[0].map,null);
});
test('draft accepts Maps LatLng points and leaves geometry untouched',()=>{
  const a=adapter();const points=[{lat:()=>15,lng:()=>120},{lat:()=>16,lng:()=>121}];
  a.drawing.update(points);assert.deepEqual(a.lines[0].path,[{lat:15,lng:120},{lat:16,lng:121}]);assert.equal(typeof points[0].lat,'function');
  const svg=guide.dotSvg(a.document,4,true);assert.ok(svg.children.some(el=>el.textContent==='5'));assert.ok(svg.children.some(el=>el.attrs.fill==='#FACC15'));
});