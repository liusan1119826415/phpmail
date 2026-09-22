import {
    Raycaster,
    Vector2,
    Vector3,
    WebGLRenderTarget,
    Color,
    BackSide,
    ShaderMaterial,
    Quaternion,
    Group,
    FrontSide,
    Box3,
} from 'three'


import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { ShaderPass } from 'three/addons/postprocessing/ShaderPass.js';
import { OutlinePass } from 'three/addons/postprocessing/OutlinePass.js';
import { FXAAShader } from 'three/addons/shaders/FXAAShader.js';
// import { SMAAEffect } from 'three/addons/postprocessing/SMAAEffect.js';
import { GammaCorrectionShader } from 'three/addons/shaders/GammaCorrectionShader.js';

import { OutlineShader } from './shader.js';

export default class Event {
    dom;
    scene;
    renderer;
    camera;

    composer;
    renderPass;
    outlinePass_hover;
    effectFXAA;

    _hoverObjects = [];
    _preHoverObjects = [];
    _selectObjects = [];
    _preSelectObjects = [];
    _canClickObjects = [];

    // partColor = 0x2ab27b;
    partColor = 0x000cb0;
    objectColor = 0xF68517;

    _clickCallback = null;

    raycaster = new Raycaster();
    mouse = new Vector2();

    _downKey = null;

    outLineShader = null;
    shadeMaterial = null;
    worldPosition = new Vector3();
    worldQuaternion = new Quaternion();
    worldScale = new Vector3();

    constructor(dom, scene, renderer, camera) {
        if (Event.instance) {
            return Event.instance;
        }

        this.dom = dom;
        this.scene = scene;
        this.renderer = renderer;
        this.camera = camera;

        this.initOutline();
        this.initShader();

        this.bindEvent();

        Event.instance = this;
    }

    initOutline() {
        const { clientWidth, clientHeight } = this.dom;

        const renderTarget = new WebGLRenderTarget(clientWidth, clientHeight, {
            samples: 4, // 多重采样
        });

        this.composer = new EffectComposer(this.renderer, renderTarget);
        // this.composer = new EffectComposer(this.renderer);

        this.renderPass = new RenderPass(this.scene, this.camera);
        this.renderPass.toneMapping = this.renderer.toneMapping;
        this.renderPass.toneMappingExposure = this.renderer.toneMappingExposure;
        this.composer.addPass(this.renderPass);

        // this.renderer.setSize(clientWidth, clientHeight);

        this.outlinePass_hover = new OutlinePass(new Vector2(clientWidth, clientHeight), this.scene, this.camera);
        this.outlinePass_hover.edgeStrength = 3.0; // 边框的亮度
        this.outlinePass_hover.edgeGlow = 0.5; // 光晕[0,1]
        this.outlinePass_hover.edgeThickness = 1.0; // 边框宽度
        this.outlinePass_hover.pulsePeriod = 3; // 呼吸闪烁的速度
        this.outlinePass_hover.visibleEdgeColor.set(this.objectColor);
        this.outlinePass_hover.hiddenEdgeColor.set(this.objectColor);
        this.composer.addPass(this.outlinePass_hover);
        this.outlinePass_hover.selectedObjects = this._hoverObjects;

        // this.outlinePass_select = new OutlinePass(new Vector2(clientWidth, clientHeight), this.scene, this.camera);
        // this.outlinePass_select.edgeStrength = 3.0; // 边框的亮度
        // this.outlinePass_select.edgeGlow = 0.5; // 光晕[0,1]
        // this.outlinePass_select.edgeThickness = 1.0; // 边框宽度
        // this.outlinePass_select.pulsePeriod = 0; // 呼吸闪烁的速度
        this.outlinePass_select = new OutlinePass(new Vector2(clientWidth, clientHeight), this.scene, this.camera);
        this.outlinePass_select.edgeStrength = 5.0; // 边框的亮度
        this.outlinePass_select.edgeGlow = 0; // 光晕[0,1]
        this.outlinePass_select.edgeThickness = 3.0; // 边框宽度
        this.outlinePass_select.pulsePeriod = 0; // 呼吸闪烁的速度
        this.outlinePass_select.visibleEdgeColor.set(this.partColor);
        this.outlinePass_select.hiddenEdgeColor.set(this.partColor);
        this.composer.addPass(this.outlinePass_select);
        this.outlinePass_select.selectedObjects = this._selectObjects;

        // this.effectFXAA = new ShaderPass(FXAAShader);
        // this.effectFXAA.uniforms['resolution'].value.set(1 / clientWidth, 1 / clientHeight);
        // this.composer.addPass(this.effectFXAA);

        // const smaaEffect = new SMAAEffect();
        // this.composer.addPass(smaaEffect);

        if (!this.gammaPass) {
            this.gammaPass = new ShaderPass(GammaCorrectionShader);
        }
        this.composer.addPass(this.gammaPass);


    }

    initShader() {
        this._shaderGroup = new Group;
        this.scene.add(this._shaderGroup);
        this.outLineShader = OutlineShader(0.004, new Color(this.partColor), BackSide);
        this.shadeMaterial = new ShaderMaterial(this.outLineShader);
    }

    setClickableObjects(objects) {
        this.clearShader();
        this._canClickObjects = objects;
    }

    getVisibleObjects() {
        return this._canClickObjects.filter(x => x.material.visible);
    }

    hoverObjects(objects) {
        this._hoverObjects.length = 0;
        objects.map(x => this._hoverObjects.push(x));

        this._preHoverObjects.map(x => {
            x.material.emissive.set(0x000000);
            x.material._oriExissive = 0x000000;
        })
        this._hoverObjects.map((x) => {
            x.material.emissive.set(this.objectColor);
            x.material._oriExissive = this.objectColor;
        })

        this._preHoverObjects = [...this._hoverObjects];
    };

    selectObjects(objects) {
        this._selectObjects.length = 0;
        objects.map(x => this._selectObjects.push(x));

        // this._preSelectObjects.map(x => {
        //     x.material.emissive.set(0x000000);
        //     x.material._oriExissive = 0x000000;
        // })
        // this._selectObjects.map((x) => {
        //     x.material.emissive.set(this.partColor);
        //     x.material._oriExissive = this.partColor;
        // })
        // this._preSelectObjects = [...this._selectObjects];



        this._preSelectObjects.map(x => {
            x.geometry.dispose();
            x.parent.remove(x);
        })

        this._preSelectObjects = [];
        this._selectObjects.map((x) => {

            /** outLineShader */
            const outlineMesh = x.clone();
            outlineMesh.material = this.shadeMaterial;

            x.updateMatrixWorld();
            x.getWorldPosition(this.worldPosition);
            outlineMesh.position.copy(this.worldPosition);

            x.getWorldQuaternion(this.worldQuaternion);
            outlineMesh.quaternion.copy(this.worldQuaternion)

            /** 绝对缩放 */
            x.getWorldScale(this.worldScale);
            outlineMesh.scale.copy(this.worldScale);

            // console.log(`===worldScale===_x:${this.worldScale.x.toFixed(4)}_y:${this.worldScale.y.toFixed(4)}_z:${this.worldScale.z.toFixed(4)}`);
            // console.log(`===outlineMesh===_x:${outlineMesh.scale.x.toFixed(4)}_y:${outlineMesh.scale.y.toFixed(4)}_z:${outlineMesh.scale.z.toFixed(4)}`);

            /** 使用相对缩放 */
            // const originalScale = new Vector3();
            // x.getWorldScale(originalScale);
            // const outlineScale = new Vector3();
            // outlineMesh.getWorldScale(outlineScale);
            // outlineMesh.scale.x = originalScale.x / outlineScale.x;
            // outlineMesh.scale.y = originalScale.y / outlineScale.y;
            // outlineMesh.scale.z = originalScale.z / outlineScale.z;

            this._shaderGroup.add(outlineMesh);
            this._preSelectObjects.push(outlineMesh);
        })
    };



    refresh() {
        if (this.composer) this.composer.render();
    }

    bindEvent() {
        this.dom.addEventListener('pointerdown', this.onPointDown);
        this.dom.addEventListener('pointermove', this.onPointMove);
        this.dom.addEventListener('pointerup', this.onPointUp);
        window.addEventListener('keydown', this.onKeyDown);
        window.addEventListener('keyup', this.onKeyUp);
    }

    unBindEvent() {
        this.dom.removeEventListener('pointerdown', this.onPointDown);
        this.dom.removeEventListener('pointermove', this.onPointMove);
        this.dom.removeEventListener('pointerup', this.onPointUp);
        window.removeEventListener('keydown', this.onKeyDown);
        window.removeEventListener('keyup', this.onKeyUp);
    }

    setClickCallback(callback) {
        this._clickCallback = callback;
    }


    onPointDown = (event) => {
        this._mouseDown = true;

        this._isMove = false;
    }

    onPointMove = (event) => {
        this._isMove = true;

        if (this._mouseDown) return;

        this._moveEvent = event;

        this.hover();

    }

    hover() {
        if (this._downKey != "Control" && this._downKey != "Shift") {
            this._preHoverObj?.material.emissive.set(this._preHoverObj?.material._oriExissive || 0x000000);
            return;
        }

        if (!this._moveEvent) return;

        const hoverObj = this.getRayObj(this._moveEvent);

        this._preHoverObj?.material.emissive.set(this._preHoverObj?.material._oriExissive || 0x000000);

        hoverObj?.material.emissive.set(this.objectColor);

        this._preHoverObj = hoverObj;
    }

    onPointUp = (event) => {

        this._mouseDown = false;

        if (this._isMove) return;

        const clickObj = this.getRayObj(event);

        // if (!clickObj) return;

        if (!this._clickCallback) {
            console.warn("===three stage=== 请先设置点击回调");
            return;
        }

        this._clickCallback(clickObj?.name, this._downKey);
    }

    onKeyDown = (event) => {
        this._downKey = event.key;
        this.hover();
    }

    onKeyUp = (event) => {
        this._downKey = null;
        this.hover();
    }


    getRayObj = (event) => {

        const width = this.dom.clientWidth;
        const height = this.dom.clientHeight;

        const { left, top } = this.dom.getBoundingClientRect();

        this.renderer.setSize(width, height);

        this.mouse.x = ((event.clientX - left) / width) * 2 - 1;
        this.mouse.y = -((event.clientY - top) / height) * 2 + 1;


        this.raycaster.setFromCamera(this.mouse, this.camera);

        const intersects = this.raycaster.intersectObjects(this.getVisibleObjects(),true);

        //#region debugger
        //--------
        // console.log(intersects);
        // const rayHelper = new ArrowHelper(this.raycaster.ray.direction, this.raycaster.ray.origin, 10, 0xff0000);
        // this.#scene.add(rayHelper);
        //--------
        //#endregion

        return intersects[0]?.object;

    }

    clearShader() {
        
        while(this._shaderGroup.children.length){
            this._shaderGroup.children[0].geometry.dispose();
            this._shaderGroup.children[0].parent.remove(this._shaderGroup.children[0]);
        }

        this._shaderGroup.removeFromParent();

        this._preSelectObjects = [];
    }

    showShader() {
        if (this.shadeMaterial) this.shadeMaterial.visible = true;
    }

    hideShader() {
        if (this.shadeMaterial) this.shadeMaterial.visible = false;
    }

    dispose() {
        this.unBindEvent();

        this.composer.removePass(this.renderPass);
        this.composer.removePass(this.outlinePass_hover);
        this.composer.removePass(this.outlinePass_select);
        if (this.effectFXAA) this.composer.removePass(this.effectFXAA);
        this.outlinePass_hover.dispose();
        this.outlinePass_select.dispose();
        if (this.effectFXAA) this.effectFXAA.fsQuad._mesh.geometry.dispose();
        this.composer.renderTarget1.dispose();
        this.composer.renderTarget2.dispose();

        if (this.shadeMaterial) this.shadeMaterial.dispose();
        this.clearShader();
    }
}