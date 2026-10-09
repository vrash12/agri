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
      Object.entries({x:39,y:26,fill:'#20362C','font-family':'Arial, sans-serif','font-size':12,'font-weight':600}).forEach(([key,value])=>text.setAttribute(key,value));
      text.textContent=name;node.appendChild(text);
    }
    return node;
  }
  function template(document, code) {
    const node=document.createElement('template');node.content.appendChild(svg(document,code,true));return node;
  }
  // A bounded horizontal scan finds an interior anchor even for concave areas.
  // A bounding-box center or arithmetic centroid can sit outside such boundaries.
  function position(polygon) {
    if (!Array.isArray(polygon) || polygon.length < 3 || polygon.length > 50) return null;
    if(polygon.some(p=>!p || p.lat === null || p.lng === null))return null;
    const ring=polygon.map(p=>({lat:Number(p.lat),lng:Number(p.lng)}));
    if (ring.some(p=>!Number.isFinite(p.lat)||!Number.isFinite(p.lng)||Math.abs(p.lat)>90||Math.abs(p.lng)>180)) return null;
    const ys=ring.map(p=>p.lat),min=Math.min(...ys),max=Math.max(...ys);
    if (max===min) return null;
    let best=null,width=0;
    const rows=Array.from({length:15},(_,i)=>min+(max-min)*(i+.5)/15);
    const distinct=[...new Set(ys)].sort((a,b)=>a-b);
    for(let i=1;i<distinct.length;i++)rows.push((distinct[i-1]+distinct[i])/2);
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
  function overlay(maps, document, map, area, select) {
    if (!maps.OverlayView || !maps.LatLng) return null;
    const anchor=position(area.polygon);if(!anchor)return null;
    const badge=document.createElement('button');badge.type='button';badge.className='crop-map-badge';
    const name=descriptor(area.crop)[0];badge.setAttribute('aria-label','Select '+name+' crop area');badge.title=name+(area.name?' · '+area.name:'');
    badge.appendChild(svg(document,area.crop,false));
    const label=document.createElement('span');label.textContent=name;badge.appendChild(label);
    badge.addEventListener('click',event=>{event.stopPropagation();select();});
    maps.OverlayView.preventMapHitsAndGesturesFrom?.(badge);
    const marker=new maps.OverlayView();let polygon=area.polygon;
    marker.onAdd=()=>marker.getPanes().overlayMouseTarget.appendChild(badge);
    marker.draw=()=>{
      const projection=marker.getProjection();if(!projection)return;
      const p=projection.fromLatLngToDivPixel(new maps.LatLng(anchor.lat,anchor.lng));if(!p)return;
      badge.style.left=p.x+'px';badge.style.top=p.y+'px';
      const corners=polygon.map(v=>projection.fromLatLngToDivPixel(new maps.LatLng(v.lat,v.lng))).filter(Boolean);
      badge.hidden=corners.length<3 || Math.min(Math.max(...corners.map(v=>v.x))-Math.min(...corners.map(v=>v.x)),Math.max(...corners.map(v=>v.y))-Math.min(...corners.map(v=>v.y)))<26;
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
      update:points=>{polygon=points;const point=position(points);if(point){Object.assign(anchor,point);marker.draw();}else badge.hidden=true;}
    };
  }
  return {descriptor,mapContext,svg,template,position,overlay};
});
