const test=require('node:test');
const assert=require('node:assert/strict');
const badges=require('../../public/js/crop-area-badges.js');
function inside(point,ring){let found=false;for(let i=0,j=ring.length-1;i<ring.length;j=i++){const a=ring[i],b=ring[j];if((a.lat>point.lat)!==(b.lat>point.lat)&&point.lng<(b.lng-a.lng)*(point.lat-a.lat)/(b.lat-a.lat)+a.lng)found=!found;}return found;}
const rectangle=[{lat:0,lng:0},{lat:0,lng:4},{lat:4,lng:4},{lat:4,lng:0}];
test('return-to-map context preserves a valid saved season and rejects unsafe parameters',()=>{
  assert.deepEqual(badges.mapContext('?crop_year=2025&crop_season=wet&map_farmer=4019',2026),{year:2025,season:'wet',farmerId:'4019'});
  assert.deepEqual(badges.mapContext('?crop_year=9999&crop_season=anything&map_farmer=<script>',2026),{year:2026,season:'dry',farmerId:null});
  assert.deepEqual(badges.mapContext('',2026),{year:2026,season:'dry',farmerId:null});
});
test('badge anchor stays inside rectangles, concave notches and reversed rings',()=>{
  const concave=[{lat:0,lng:0},{lat:0,lng:4},{lat:4,lng:4},{lat:4,lng:3},{lat:1,lng:3},{lat:1,lng:1},{lat:4,lng:1},{lat:4,lng:0}];
  for(const ring of [rectangle,concave,concave.slice().reverse()])assert.ok(inside(badges.position(ring),ring));
  assert.deepEqual(badges.position(rectangle),{lat:2,lng:2});
});
test('invalid or oversized geometry has no badge and unknown crops use a safe icon',()=>{
  for(const ring of [null,[],Array(51).fill({lat:0,lng:0}),[{lat:91,lng:0},{lat:1,lng:1},{lat:1,lng:0}],[null,{lat:1,lng:1},{lat:1,lng:0}],Array(3).fill({lat:0,lng:0})])assert.equal(badges.position(ring),null);
  assert.equal(badges.descriptor('<script>')[0],'Other crop');
});
function adapter(){
  const elements=[];let marker,animations=0,selected=0;
  class Element{constructor(name){this.name=name;this.attrs={};this.style={};this.children=[];this.events={};this.classList={toggle(){}};elements.push(this);}setAttribute(k,v){this.attrs[k]=String(v);}appendChild(v){this.children.push(v);}addEventListener(k,v){this.events[k]=v;}remove(){this.removed=true;}getAnimations(){return [];}animate(){animations++;}}
  const document={createElement:name=>new Element(name),createElementNS:(_,name)=>new Element(name)};
  class OverlayView{constructor(){marker=this;}getPanes(){return {overlayMouseTarget:{appendChild(){}}};}getProjection(){return {fromLatLngToDivPixel:p=>({x:p.lng*100,y:p.lat*100})};}setMap(map){if(map){this.onAdd();this.draw();}else this.onRemove();}}
  class LatLng{constructor(lat,lng){this.lat=lat;this.lng=lng;}}
  const area={crop:'corn',name:'<img onerror=alert(1)>',polygon:rectangle};
  const overlay=badges.overlay({OverlayView,LatLng},document,{},area,()=>selected++);
  return {overlay,button:elements.find(el=>el.name==='button'),elements,document,get animations(){return animations;},get selected(){return selected;}};
}
test('SVG crop stickers use static safe paths and expose a text label',()=>{
  const a=adapter();const svg=badges.svg(a.document,'corn',true);
  assert.equal(svg.children.find(child=>child.name==='text').textContent,'Corn');
  assert.ok(svg.children.some(child=>child.attrs.fill==='#FFD45A'));
  assert.equal(a.button.attrs['aria-label'],'Select Corn crop area');
  assert.equal(a.elements.some(el=>el.name==='script'||el.name==='img'),false);
});
test('badge follows edited geometry, selects the area and cleans up when removed',()=>{
  const a=adapter();assert.equal(a.button.style.left,'200px');
  a.button.events.click({stopPropagation(){}});assert.equal(a.selected,1);
  a.overlay.update(rectangle.map(p=>({lat:p.lat+1,lng:p.lng+1})));assert.equal(a.button.style.left,'300px');
  a.overlay.setDisabled(true);assert.equal(a.button.style.visibility,'hidden');
  a.overlay.setDisabled(false);a.overlay.select(true);assert.equal(a.button.attrs['aria-pressed'],'true');
  a.overlay.remove();assert.equal(a.button.removed,true);
});
test('badge animation is finite and is skipped for reduced motion',()=>{
  const previous=global.matchMedia;
  try{
    global.matchMedia=()=>({matches:false});const a=adapter();a.overlay.animate();assert.equal(a.animations,1);
    global.matchMedia=()=>({matches:true});a.overlay.animate();assert.equal(a.animations,1);
  }finally{global.matchMedia=previous;}
});
