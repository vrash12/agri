(function (root, factory) {
  'use strict';
  const api = factory(root);
  root.CropAreaBadges = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis, function (root) {
  'use strict';
  const crops = {
    rice: ['Rice / Palay', '#219653', 'M12 22V3 M12 8C7 8 5 5 5 2C9 2 12 5 12 8 M12 13C7 13 5 10 5 7 M12 18C7 18 5 15 5 12 M12 8C17 8 19 5 19 2 M12 13C17 13 19 10 19 7 M12 18C17 18 19 15 19 12'],
    corn: ['Corn', '#D4A017', 'M8 5C8 1 16 1 16 5V14C16 20 8 20 8 14Z M8 7H16 M8 11H16 M8 15H16 M12 3V18 M12 22C5 20 3 15 3 10C8 11 11 15 12 22 M12 22C19 20 21 15 21 10C16 11 13 15 12 22'],
    vegetables: ['Vegetables', '#8E44AD', 'M12 22V12 M12 17C3 17 2 10 3 4C10 4 13 9 12 17 M12 13C12 5 18 2 22 3C23 9 19 14 12 13 M5 7L12 17 M19 6L12 13'],
    root_crops: ['Root crops', '#A65C32', 'M10 7L16 10L8 22L6 20Z M12 8L11 2 M14 8L18 3 M11 12L14 13 M8 17L10 18'],
    fruit: ['Fruit', '#DB5B36', 'M12 9C5 3 1 11 5 18C8 24 11 20 12 20C14 20 17 24 20 18C24 11 20 3 12 9 M12 8V3 M12 5C14 1 18 1 20 2C18 6 15 7 12 5'],
    legumes: ['Legumes', '#168C94', 'M20 3C9 1 2 11 4 20C13 23 23 12 20 3Z M7 16C7 12 10 12 10 15C10 18 7 19 7 16 M11 11C11 7 14 7 14 10C14 13 11 14 11 11 M15 7C15 4 18 4 18 6C18 9 15 10 15 7'],
    mixed: ['Mixed crops', '#4263C7', 'M12 22V10 M12 16C4 17 2 10 3 5C10 5 13 10 12 16 M12 12C12 4 18 2 21 3C22 8 18 13 12 12 M5 8L12 16 M17 6L12 12'],
    other: ['Other crop', '#C44379', 'M12 22V10 M12 15C4 15 3 8 3 4C10 4 13 9 12 15 M12 10C12 3 18 2 21 3C21 8 17 12 12 10']
  };
  function descriptor(code) { return crops[code] || crops.other; }
  function mapContext(search,currentYear) {
    const params=new URLSearchParams(search), year=Number(params.get('crop_year'));
    const farmer=params.get('map_farmer') || '';
    return {year:Number.isInteger(year)&&year>=1990&&year<=currentYear+1?year:currentYear,
      season:params.get('crop_season')==='wet'?'wet':'dry',
      farmerId:/^[1-9]\d{0,15}$/.test(farmer)&&Number.isSafeInteger(Number(farmer))?farmer:null};
  }
  function svg(document, code, label) {
    const [name, color, path] = descriptor(code);
    const node = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    node.setAttribute('viewBox', label ? '0 0 130 44' : '0 0 24 24');
    node.setAttribute('width', label ? '130' : '24'); node.setAttribute('height', label ? '44' : '24');
    node.setAttribute('aria-hidden', 'true');
    if (label) {
      const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      Object.entries({x:1,y:1,width:128,height:42,rx:12,fill:'#FFFFFF',stroke:'#20362C','stroke-width':2}).forEach(([key,value])=>rect.setAttribute(key,value));
      node.appendChild(rect);
    }
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    const palette={rice:['#FBE49A','#315B33'],corn:['#FFD45A','#6F4A13'],vegetables:['#A7D878','#315B33'],root_crops:['#FFA859','#713D22'],fruit:['#F98A74','#7B332D'],legumes:['#B7DC83','#315B33'],mixed:['#ABD98E','#315B33'],other:['#ABD98E','#315B33']};
    const [fill,outline]=palette[code] || palette.other;
    Object.entries({d:path,fill:fill,stroke:outline,'stroke-width':1.9,'stroke-linecap':'round','stroke-linejoin':'round'}).forEach(([key,value])=>icon.setAttribute(key,value));
    if (label) icon.setAttribute('transform','translate(8 10)');
    node.appendChild(icon);
    if(code==='corn' || code==='fruit' || code==='root_crops') {
      const leaf=document.createElementNS('http://www.w3.org/2000/svg','path');
      const leaves={corn:'M12 22C5 20 3 15 3 10C8 11 11 15 12 22 M12 22C19 20 21 15 21 10C16 11 13 15 12 22',fruit:'M12 5C14 1 18 1 20 2C18 6 15 7 12 5',root_crops:'M12 8L11 2 M14 8L18 3'};
      Object.entries({d:leaves[code],fill:'#8AC767',stroke:'#315B33','stroke-width':1.9,'stroke-linecap':'round','stroke-linejoin':'round'}).forEach(([key,value])=>leaf.setAttribute(key,value));
      if(label)leaf.setAttribute('transform','translate(8 10)');node.appendChild(leaf);
    }
    if (label) {
      const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      Object.entries({x:39,y:26,fill:'#20362C','font-family':'Inter, Arial, sans-serif','font-size':12,'font-weight':600}).forEach(([key,value])=>text.setAttribute(key,value));
      text.textContent=name;node.appendChild(text);
    }
    return node;
  }
  function template(document, code) {
    const node=document.createElement('template');node.content.appendChild(svg(document,code,true));return node;
  }
  function markerContent(document, code) {
    // Maps 3D reads custom SVG graphics from a direct-child HTML template.
    return template(document, code);
  }
  // A bounded horizontal scan finds an interior anchor even for concave areas.
  // A bounding-box center or arithmetic centroid can sit outside such boundaries.
  function position(polygon, limit = 50, vertexRows = true) {
    if (!Array.isArray(polygon) || polygon.length < 3 || polygon.length > limit) return null;
    if(polygon.some(p=>!p || p.lat === null || p.lng === null))return null;
    const ring=polygon.map(p=>({lat:Number(p.lat),lng:Number(p.lng)}));
    if (ring.some(p=>!Number.isFinite(p.lat)||!Number.isFinite(p.lng)||Math.abs(p.lat)>90||Math.abs(p.lng)>180)) return null;
    const ys=ring.map(p=>p.lat),min=Math.min(...ys),max=Math.max(...ys);
    if (max===min) return null;
    let best=null,width=0;
    const rows=Array.from({length:15},(_,i)=>min+(max-min)*(i+.5)/15);
    if (vertexRows) {
      const distinct=[...new Set(ys)].sort((a,b)=>a-b);
      for(let i=1;i<distinct.length;i++)rows.push((distinct[i-1]+distinct[i])/2);
    }
    rows.forEach(y=>{
      const intersections=[];
      for(let i=0,j=ring.length-1;i<ring.length;j=i++) {
        const a=ring[j],b=ring[i];
        if((a.lat>y)!==(b.lat>y))intersections.push(a.lng+(y-a.lat)*(b.lng-a.lng)/(b.lat-a.lat));
      }
      intersections.sort((a,b)=>a-b);
      for(let i=0;i+1<intersections.length;i+=2) {
        const span=intersections[i+1]-intersections[i];
        // Prefer central rows when equally wide, keeping rectangles centered.
        if(span>0&&(!best || span>width+1e-12 || (Math.abs(span-width)<1e-12&&Math.abs(y-(min+max)/2)<Math.abs(best.lat-(min+max)/2)))) {
          width=span;best={lat:y,lng:(intersections[i]+intersections[i+1])/2};
        }
      }
    });
    return best;
  }
  // Compute once per loaded parcel, using fixed scan rows for large rings.
  function parcelPosition(polygon) { return position(polygon, 10000, false); }
  function parcelCrops(record) {
    if (!record || record.crop === 'not_recorded' || !Object.hasOwn(crops, record.crop)) return [];
    const codes = Array.isArray(record.area_crops) && record.area_crops.length
      ? record.area_crops : [record.crop];
    return [...new Set(codes.filter(code => Object.hasOwn(crops, code)))].slice(0, 8);
  }
  function parcelMarkerContent(document, codes, options) {
    const selected = [...new Set(codes.filter(code => Object.hasOwn(crops, code)))].slice(0, 8);
    const compact = !!options?.compact;
    const gap = compact ? 18 : 26;
    const iconSize = compact ? 16 : 24;
    const height = compact ? 24 : 36;
    const template = document.createElement('template');
    const node = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    const width = selected.length * gap + (compact ? 8 : 12);
    Object.entries({viewBox:`0 0 ${width} ${height}`,width:String(width),height:String(height),'aria-hidden':'true'}).forEach(([key,value])=>node.setAttribute(key,value));
    const frame = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
    Object.entries({x:1,y:1,width:width-2,height:height-2,rx:compact ? 7 : 9,fill:'#FFFFFF',stroke:'#20362C','stroke-width':compact ? 1.5 : 2}).forEach(([key,value])=>frame.setAttribute(key,value));
    node.appendChild(frame);
    selected.forEach((code,index)=>{
      const icon = svg(document,code,false);
      icon.setAttribute('width',String(iconSize)); icon.setAttribute('height',String(iconSize));
      icon.setAttribute('x',String(compact ? 4 + index * gap : 6 + index * gap));
      icon.setAttribute('y',String(compact ? 4 : 6));
      node.appendChild(icon);
    });
    template.content.appendChild(node);
    return template;
  }
  function linePosition(path) {
    if (!Array.isArray(path) || path.length < 2 || path.length > 50) return null;
    const points = path.map(point => ({lat:Number(point?.lat),lng:Number(point?.lng)}));
    if (points.some(point => !Number.isFinite(point.lat) || !Number.isFinite(point.lng) || Math.abs(point.lat)>90 || Math.abs(point.lng)>180)) return null;
    const middle = Math.floor((points.length - 1) / 2);
    return {lat:(points[middle].lat + points[middle + 1].lat) / 2, lng:(points[middle].lng + points[middle + 1].lng) / 2};
  }
  function anchor(area) {
    return area?.type === 'line' || Array.isArray(area?.path) ? linePosition(area.path) : position(area?.polygon);
  }
  function containsPath(path, parcel) {
    if (!Array.isArray(path) || path.length < 2 || path.length > 50 || !Array.isArray(parcel) || parcel.length < 3 || parcel.length > 1000) return false;
    const points=path.map(p=>({lat:Number(p?.lat),lng:Number(p?.lng)}));
    const boundary=parcel.map(p=>({lat:Number(p?.lat),lng:Number(p?.lng)}));
    if(points.some(p=>!Number.isFinite(p.lat)||!Number.isFinite(p.lng)) || boundary.some(p=>!Number.isFinite(p.lat)||!Number.isFinite(p.lng)))return false;
    const cross=(a,b,c)=>(b.lng-a.lng)*(c.lat-a.lat)-(b.lat-a.lat)*(c.lng-a.lng);
    const on=(a,b,p)=>Math.abs(cross(a,b,p))<1e-12 && p.lng>=Math.min(a.lng,b.lng)-1e-10 && p.lng<=Math.max(a.lng,b.lng)+1e-10 && p.lat>=Math.min(a.lat,b.lat)-1e-10 && p.lat<=Math.max(a.lat,b.lat)+1e-10;
    const inside=p=>{
      let result=false;
      for(let i=0,j=boundary.length-1;i<boundary.length;j=i++){
        const a=boundary[j],b=boundary[i];
        if(on(a,b,p))return true;
        if((a.lat>p.lat)!==(b.lat>p.lat) && p.lng<(b.lng-a.lng)*(p.lat-a.lat)/(b.lat-a.lat)+a.lng)result=!result;
      }
      return result;
    };
    if(points.some(point=>!inside(point)))return false;
    for(let i=0;i<points.length-1;i++){
      const a=points[i],b=points[i+1],dx=b.lng-a.lng,dy=b.lat-a.lat,denominator=dx;
      if(Math.abs(dx)+Math.abs(dy)<1e-14)continue;
      const cuts=[0,1];
      for(let j=0;j<boundary.length;j++){
        const c=boundary[j],d=boundary[(j+1)%boundary.length],ex=d.lng-c.lng,ey=d.lat-c.lat,det=dx*ey-dy*ex;
        if(Math.abs(det)<1e-18){
          if(Math.abs(cross(a,b,c))<1e-12){
            const axis=Math.abs(dx)>=Math.abs(dy)?'lng':'lat';
            [c,d].forEach(point=>{const t=(point[axis]-a[axis])/(b[axis]-a[axis]);if(t>0&&t<1)cuts.push(t);});
          }
          continue;
        }
        const t=((c.lng-a.lng)*ey-(c.lat-a.lat)*ex)/det;
        const u=((c.lng-a.lng)*dy-(c.lat-a.lat)*dx)/det;
        if(t>0&&t<1&&u>=0&&u<=1)cuts.push(t);
      }
      cuts.sort((x,y)=>x-y);
      for(let j=1;j<cuts.length;j++){
        const t=(cuts[j-1]+cuts[j])/2;
        if(!inside({lat:a.lat+dy*t,lng:a.lng+dx*t}))return false;
      }
    }
    return true;
  }
  // Display-only enclosure: never replace stored line points or calculate hectares.
  function shadedRing(path, parcel) {
    if (!linePosition(path) || !Array.isArray(parcel) || parcel.length < 3 || parcel.length > 1000) return null;
    if(parcel.some(p=>!Number.isFinite(p?.lat)||!Number.isFinite(p?.lng)||Math.abs(p.lat)>90||Math.abs(p.lng)>180))return null;
    const ring=path.map(p=>({lat:Number(p.lat),lng:Number(p.lng)}));
    if(ring[0].lat===ring.at(-1).lat && ring[0].lng===ring.at(-1).lng)ring.pop();
    if(ring.length<3 || !position(ring))return null;
    if(!containsPath([...ring,ring[0]],parcel))return null;
    const cross=(a,b,c)=>(b.lng-a.lng)*(c.lat-a.lat)-(b.lat-a.lat)*(c.lng-a.lng);
    const on=(a,b,p)=>Math.abs(cross(a,b,p))<1e-12 && p.lng>=Math.min(a.lng,b.lng)-1e-10 && p.lng<=Math.max(a.lng,b.lng)+1e-10 && p.lat>=Math.min(a.lat,b.lat)-1e-10 && p.lat<=Math.max(a.lat,b.lat)+1e-10;
    const intersects=(a,b,c,d)=>{
      const abC=cross(a,b,c),abD=cross(a,b,d),cdA=cross(c,d,a),cdB=cross(c,d,b);
      return (abC*abD<0 && cdA*cdB<0) || on(a,b,c) || on(a,b,d) || on(c,d,a) || on(c,d,b);
    };
    for(let i=0;i<ring.length;i++)for(let j=i+1;j<ring.length;j++){
      if(j===i+1 || (i===0 && j===ring.length-1))continue;
      if(intersects(ring[i],ring[(i+1)%ring.length],ring[j],ring[(j+1)%ring.length]))return null;
    }
    const inside=p=>{
      let result=false;
      for(let i=0,j=parcel.length-1;i<parcel.length;j=i++){
        const a=parcel[j],b=parcel[i];
        if(!Number.isFinite(a?.lat)||!Number.isFinite(a?.lng))return false;
        if(on(a,b,p))return true;
        if((a.lat>p.lat)!==(b.lat>p.lat) && p.lng<(b.lng-a.lng)*(p.lat-a.lat)/(b.lat-a.lat)+a.lng)result=!result;
      }
      return result;
    };
    for(let i=0;i<ring.length;i++){
      const a=ring[i],b=ring[(i+1)%ring.length],cuts=[0,1];
      if(!inside(a))return null;
      for(let j=0;j<parcel.length;j++){
        const c=parcel[j],d=parcel[(j+1)%parcel.length];
        const denominator=(b.lng-a.lng)*(d.lat-c.lat)-(b.lat-a.lat)*(d.lng-c.lng);
        if(Math.abs(denominator)<1e-18)continue;
        const t=((c.lng-a.lng)*(d.lat-c.lat)-(c.lat-a.lat)*(d.lng-c.lng))/denominator;
        const u=((c.lng-a.lng)*(b.lat-a.lat)-(c.lat-a.lat)*(b.lng-a.lng))/denominator;
        if(t>0&&t<1&&u>=0&&u<=1)cuts.push(t);
      }
      cuts.sort((x,y)=>x-y);
      for(let j=1;j<cuts.length;j++){
        const t=(cuts[j-1]+cuts[j])/2;
        if(!inside({lat:a.lat+(b.lat-a.lat)*t,lng:a.lng+(b.lng-a.lng)*t}))return null;
      }
    }
    return ring;
  }
  function sections(areas, parcel) {
    const rings=areas.map(area=>area?.type==='line'||Array.isArray(area?.path) ? shadedRing(area.path,parcel) : area?.polygon || null);
    const conflicts=[];
    const spans=(ring,y)=>{
      const crossings=[];
      for(let i=0,j=ring.length-1;i<ring.length;j=i++){
        const a=ring[j],b=ring[i];
        if((a.lat>y)!==(b.lat>y))crossings.push(a.lng+(b.lng-a.lng)*(y-a.lat)/(b.lat-a.lat));
      }
      return crossings.sort((a,b)=>a-b);
    };
    for(let i=0;i<rings.length;i++)for(let j=i+1;j<rings.length;j++){
      if(!rings[i]||!rings[j])continue;
      const rows=[...new Set([...rings[i],...rings[j]].map(p=>p.lat))].sort((a,b)=>a-b);
      let overlap=false;
      const side=(a,b,p)=>(b.lng-a.lng)*(p.lat-a.lat)-(b.lat-a.lat)*(p.lng-a.lng);
      for(let x=0;x<rings[i].length&&!overlap;x++)for(let z=0;z<rings[j].length;z++){
        const a=rings[i][x],b=rings[i][(x+1)%rings[i].length],c=rings[j][z],d=rings[j][(z+1)%rings[j].length];
        if(side(a,b,c)*side(a,b,d)<0 && side(c,d,a)*side(c,d,b)<0)overlap=true;
      }
      for(let k=1;k<rows.length&&!overlap;k++){
        const y=(rows[k-1]+rows[k])/2,a=spans(rings[i],y),b=spans(rings[j],y);
        for(let x=0;x<a.length;x+=2)for(let z=0;z<b.length;z+=2){
          if(Math.min(a[x+1],b[z+1])-Math.max(a[x],b[z])>1e-10)overlap=true;
        }
      }
      if(overlap)conflicts.push([i,j]);
    }
    return {rings,conflicts};
  }
  function overlay(maps, document, map, area, select) {
    if (!maps.OverlayView || !maps.LatLng) return null;
    const line = area?.type === 'line' || Array.isArray(area?.path);
    const points = line ? area.path : area.polygon;
    const anchorPoint=anchor(area);if(!anchorPoint)return null;
    const badge=document.createElement('button');badge.type='button';badge.className='crop-map-badge';
    const name=descriptor(area.crop)[0];badge.setAttribute('aria-label','Select '+name+' crop '+(area?.type==='line' || Array.isArray(area?.path) ? 'mark' : 'area'));badge.title=name+(area.name?' · '+area.name:'');
    badge.appendChild(svg(document,area.crop,false));
    const label=document.createElement('span');label.textContent=name;badge.appendChild(label);
    badge.addEventListener('click',event=>{event.stopPropagation();select();});
    maps.OverlayView.preventMapHitsAndGesturesFrom?.(badge);
    const marker=new maps.OverlayView();let polygon=points;
    marker.onAdd=()=>marker.getPanes().overlayMouseTarget.appendChild(badge);
    marker.draw=()=>{
      const projection=marker.getProjection();if(!projection)return;
      const p=projection.fromLatLngToDivPixel(new maps.LatLng(anchorPoint.lat,anchorPoint.lng));if(!p)return;
      badge.style.left=p.x+'px';badge.style.top=p.y+'px';
      const corners=polygon.map(v=>projection.fromLatLngToDivPixel(new maps.LatLng(v.lat,v.lng))).filter(Boolean);
      const width=Math.max(...corners.map(v=>v.x))-Math.min(...corners.map(v=>v.x));
      const height=Math.max(...corners.map(v=>v.y))-Math.min(...corners.map(v=>v.y));
      // A horizontal or vertical crop line has no width in one direction.
      badge.hidden=corners.length<(line?2:3) || (line?Math.max(width,height):Math.min(width,height))<26;
    };
    marker.onRemove=()=>badge.remove();
    marker.setMap(map);
    return {remove:()=>marker.setMap(null),setDisabled:value=>{badge.disabled=value;badge.style.visibility=value?'hidden':'';},
      select:value=>{badge.setAttribute('aria-pressed',String(value));badge.classList.toggle('is-selected',value);},
      animate:()=>{
        if(root.matchMedia?.('(prefers-reduced-motion: reduce)').matches)return;
        badge.getAnimations?.().forEach(animation=>animation.cancel());
        badge.animate?.([{transform:'translate(-50%, -50%) scale(1)'},{transform:'translate(-50%, -58%) scale(1.1)'},{transform:'translate(-50%, -50%) scale(1)'}],{duration:650,iterations:2,easing:'ease-in-out'});
      },
      update:nextPoints=>{polygon=nextPoints;const point=line?linePosition(nextPoints):position(nextPoints);if(point){Object.assign(anchorPoint,point);marker.draw();}else badge.hidden=true;}
    };
  }
  return {descriptor,mapContext,svg,template,markerContent,position,parcelPosition,parcelCrops,parcelMarkerContent,linePosition,anchor,containsPath,shadedRing,sections,overlay};
});
