const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/assistance-coverage.js'), 'utf8');
const {colorFor, chunks} = require('../../public/js/assistance-coverage.js');

test('release counts, including zero, use the published legend thresholds', () => {
    assert.deepEqual([0,1,9,10,49,50,199,200].map(colorFor), ['#e2e5e7','#cce4b7','#cce4b7','#85ba70','#85ba70','#39864c','#39864c','#155b32']);
});
test('geometry requests are bounded to ten and never duplicate or omit IDs', () => {
    const ids = Array.from({length:250}, (_, i) => i+1);
    assert.equal(chunks(ids).length, 25);
    assert.deepEqual(chunks(ids).flat(), ids);
    assert.equal(chunks([]).length, 0);
});

class Element {
    constructor() { this.hidden = true; this.textContent = ''; this.listeners = {}; this.dataset = {}; this.children = []; this.value = ''; this.attributes = {}; this.classList = {toggle(){}}; }
    addEventListener(name, fn) { this.listeners[name] = fn; }
    async dispatch(name) { return this.listeners[name]?.(); }
    setAttribute(name, value) { this.attributes[name] = value; }
    appendChild(node) { this.children.push(node); }
    scrollIntoView() {}
    remove() {}
}
function setup({key='browser-key', count=12, failure=null, geometryMissing=false} = {}) {
    const button = new Element(), status = new Element(), container = new Element(), season = new Element(), year = new Element();
    season.value = 'all';
    const requestIds = [], data = new Map(), timers = new Map();
    let sequence=0, requests=0, fail=failure;
    const focusButtons = [new Element()];
    focusButtons[0].dataset.focusMunicipality = '1';
    const document = {
        querySelector: q => ({'[data-load-coverage]':button,'[data-coverage-status]':status}[q]),
        querySelectorAll: () => focusButtons,
        getElementById: id => ({'assistance-coverage-map':container, season, year}[id]),
        createElement: () => new Element(), head: new Element(),
    };
    class Bounds { extend() {} getCenter() { return {lat:15,lng:120}; } }
    const events = {};
    class MapMock {
        constructor() { this.data = {
            setStyle: fn => { events.style = fn; },
            addListener: (name, fn) => {events[name] = fn;},
            addGeoJson: collection => collection.features.map(feature => {
                const result = {getId:()=>feature.id, getGeometry:()=>({forEachLatLng:fn=>fn({lat:15,lng:120})})};
                data.set(feature.id, result); return result;
            }),
            forEach: fn => [...data.values()].forEach(fn), remove: feature => data.delete(feature.getId()), getFeatureById: id => data.get(id),
        }; }
        fitBounds() {}
    }
    const google = {maps:{Map:MapMock, LatLngBounds:Bounds, InfoWindow:class {setContent(node){ events.popup=node; } setPosition(){} open(){} close(){}}}};
    const window = {
        assistanceCoverageSettings:{key,boundariesUrl:'http://localhost/assistance-coverage/boundaries',rows:Array.from({length:count}, (_,i)=>({id:i+1,name:i===0?'<script>private</script>':'Area',releases:i,beneficiaries:i,quantities:[],boundary:geometryMissing?'missing':'available'}))},
        google, location:{origin:'http://localhost'},
        setTimeout(fn) { const id=++sequence; timers.set(id, fn); return id; }, clearTimeout(id){ timers.delete(id); },
    };
    const fetch = async url => {
        requests++;
        const ids = url.searchParams.getAll('municipality_ids[]').map(Number); requestIds.push(ids);
        if (fail === 'timeout') throw Object.assign(new Error(), {name:'AbortError'});
        if (fail === 'second' && requests === 2) return {ok:false,status:500};
        if (fail === 'auth') return {ok:false,status:401};
        return {ok:true, status:200, json:async()=>({type:'FeatureCollection', features:ids.map(id=>({id}))})};
    };
    vm.runInNewContext(source, {window,document,google,fetch,URL,AbortController,Map,Set,Promise,module:{exports:{}}});
    return {button,status,container,season,year,focusButtons,requestIds,data,events,timers,requests:()=>requests,recover:()=>{fail=null;}};
}

test('map loading is deferred, batched once, and fitting does not refetch', async () => {
    const view=setup();
    assert.equal(view.requests(),0);
    await view.button.dispatch('click');
    assert.deepEqual(view.requestIds.map(ids=>ids.length),[10,2]);
    assert.equal(view.data.size,12);
    assert.match(view.status.textContent,/12 municipality boundaries shown/);
    assert.equal(view.container.hidden,false);
    await view.button.dispatch('click');
    assert.equal(view.requests(),2);
    assert.equal(view.timers.size,0);
});
test('repeated open actions share one load instead of duplicating requests', async () => {
    const view=setup();
    await Promise.all([view.button.dispatch('click'),view.button.dispatch('click')]);
    assert.equal(view.requests(),2);
});
test('partial fetch failures clear map colors and retry from a clean state', async () => {
    const view=setup({failure:'second'});
    await view.button.dispatch('click');
    assert.equal(view.data.size,0);
    assert.equal(view.container.hidden,true);
    assert.equal(view.button.textContent,'Retry map');
    assert.equal(view.button.disabled,false);
    view.recover();
    await view.button.dispatch('click');
    assert.equal(view.data.size,12);
});
test('expired sessions and timeouts give a useful failure with the table unaffected', async () => {
    for (const [failure,text] of [['auth',/sign in again/],['timeout',/took too long/]]) {
        const view=setup({failure});
        await view.button.dispatch('click');
        assert.match(view.status.textContent,text);
        assert.equal(view.data.size,0);
        assert.equal(view.timers.size,0);
    }
});
test('unconfigured or unmapped scopes do not start a provider or boundary request', async () => {
    for (const options of [{key:''},{geometryMissing:true}]) {
        const view=setup(options);
        assert.equal(view.button.hidden,true);
        assert.equal(view.requests(),0);
        assert.match(view.status.textContent,/table|figures below/);
    }
});
test('manual municipality focus loads its map and renders names as text only', async () => {
    const view=setup();
    await view.focusButtons[0].dispatch('click');
    assert.equal(view.events.popup.children[0].textContent,'<script>private</script>');
    assert.equal(view.events.popup.children[0].innerHTML,undefined);
    assert.equal(view.events.style(view.data.get(1)).fillColor,colorFor(0));
});
test('season changes require an explicit year or disable it for unrecorded periods', async () => {
    const view=setup();
    view.season.value='wet'; await view.season.dispatch('change');
    assert.equal(view.year.required,true);
    view.season.value='unrecorded'; await view.season.dispatch('change');
    assert.equal(view.year.disabled,true);
    assert.equal(view.year.required,false);
});
