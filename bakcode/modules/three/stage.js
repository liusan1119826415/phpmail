import {
    ACESFilmicToneMapping,
    AmbientLight,
    Cache,
    Color,
    DirectionalLight,
    Group,
    LinearFilter,
    LinearMipMapLinearFilter,
    Mesh,
    MeshStandardMaterial,
    PCFSoftShadowMap,
    PerspectiveCamera,
    PlaneGeometry,
    CircleGeometry,
    PMREMGenerator,
    RepeatWrapping,
    Scene,
    Texture,
    WebGLRenderer,
    Vector3,
    PointLight,
    SpotLight,
    MeshBasicMaterial,
    BackSide,
    Clock,
    BufferGeometry,
    WebGLRenderTarget,
    HemisphereLight,
    MeshPhongMaterial,
    DoubleSide,
    ShadowMaterial,
    Box3,
    EquirectangularReflectionMapping,
    SRGBColorSpace,
    NeutralToneMapping,
    FrontSide,
    Float32BufferAttribute,
    Quaternion,
    BoxHelper,
} from "three";
// import { OrbitControls } from "three/examples/jsm/controls/OrbitControls";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import { MyGLTFLoader, MyEXRLoader } from "./loader.js";
import Stats from 'three/addons/libs/stats.module.js'
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

import Event from "./event.js";
import Tex from "./texture.js";

import { GLTFExporter } from 'three/addons/exporters/GLTFExporter.js';
import * as TextureUtils from 'three/addons/utils/WebGLTextureUtils.js';

import * as BufferGeometryUtils from 'three/addons/utils/BufferGeometryUtils.js';


// import { DRACOExporter } from 'three/addons/exporters/DRACOExporter.js';
import { DRACOLoader } from "three/addons/loaders/DRACOLoader.js"
import { DRACOExporter } from './DRACOExporter.js';
import { DracoEncoderModule } from './draco/draco_encoder.js';
// import * as DracoEncoder from './loader.js';

// import { SimplifyModifier } from 'three/addons/modifiers/SimplifyModifier.js';
import { SimplifyModifier } from './SimplifyModifier.js';
import { mergeVertices } from 'three/addons/utils/BufferGeometryUtils.js';
import { generateUUID } from "./MathUtils.js";


export default class Stage {
    dom;
    mainDom;
    scene;
    camera;
    controls;
    renderer;

    _ground;
    animateInstance = void 0;

    gltfLoader = new MyGLTFLoader();
    _model;
    _oriModel;
    _slimModel;

    _event;

    _tex = new Tex();

    gltfExporter = new GLTFExporter().setTextureUtils(TextureUtils);
    dracoExporter = new DRACOExporter();

    _clarity = 1;

    initCameraPoi = {
        x: 1,
        y: 2,
        z: 2,
    }

    _stats = new Stats();

    _box3 = new Box3();

    _whiteModelColor = 0xdddddd;

    exportMat = new MeshStandardMaterial({ color: 0xffffff });

    modifier = new SimplifyModifier();


    constructor(dom) {
        this.dom = dom;

        this.dracoExporter.setEncoderModule(DracoEncoderModule);

        /** scene */
        this.scene = new Scene();
        this.scene.background = new Color(0xFFFFFF);

        /** camera */
        const { clientWidth, clientHeight } = dom;
        this.camera = new PerspectiveCamera(65, clientWidth / clientHeight, 0.05, 1000);
        this.camera.position.set(this.initCameraPoi.x, this.initCameraPoi.y, this.initCameraPoi.z);

        /** controls */
        this.reInitControls();

        /** renderer */
        this.renderer = new WebGLRenderer({
            alpha: true,
            antialias: true, // 抗锯齿
            preserveDrawingBuffer: true, // 多重采样
        });

        this.renderer.outputColorSpace = SRGBColorSpace;

        this.renderer.physicallyCorrectLights = true;
        this.renderer.toneMapping = NeutralToneMapping;
        this.renderer.toneMappingExposure = 1;

        if (this.renderer.capabilities.isWebGL2) {
            console.warn("=== the device support webgl2 ===");
            this.renderer.sampleLevel = 8; // 设置MSAA采样级别，0 = 不使用MSAA，1 = 2x2 MSAA，2 = 4x4 MSAA，以此类推
        }else{
            console.warn("=== the device not support webgl2 ===");
        }
        this.renderer.setPixelRatio(window.devicePixelRatio * this._clarity);
        this.renderer.setSize(clientWidth, clientHeight);
        this.renderer.domElement.style.overflow = "hidden";
        this.renderer.domElement.style.border = "none";
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = PCFSoftShadowMap;
        // this.renderer.shadowMap.type = PCFShadowMap;
        dom.appendChild(this.renderer.domElement);


        /** light */
        // this.initShadowLight2();
        // this.initLight2();
        // this.initLightExr2(exr_url);

        /** ground */
        this.initGround();

        /** event */
        this._event = new Event(dom, this.scene, this.renderer, this.camera);

        /** 性能监控 */
        // this.stat(dom);

        window.addEventListener('resize', this.onWindowResize);
    }

    reInitControls() {
        this.controls = new OrbitControls(this.camera, this.dom);
        // this.controls.enablePan = false;
        this.controls.maxDistance = 5;
        this.controls.minDistance = 0.5;
        // this.controls.enableDamping = true;
        // this.controls.dampingFactor = 0.05;
        this.controls.addEventListener('change', this.onControlsChange);
        this.controls.addEventListener('end', this.onControlsEnd);
    }

    openSlim(dom,slimModel) {
        this.mainDom = this.dom;
        this.dom = dom;
        this.dom.appendChild(this.renderer.domElement);
        this.onWindowResize();
        this.reInitControls();

        this._model.removeFromParent();

        if(slimModel){
            this._slimModel = slimModel;
            this._model = this._slimModel;
            this.scene.add(this._model);
        }

        this.recordSlim(slimModel);
    }

    closeSlim() {
        this.dom = this.mainDom;
        this.dom.appendChild(this.renderer.domElement);
        this.onWindowResize();
        this.reInitControls();

        this._model.removeFromParent();
        this._model = this._oriModel;
        this.scene.add(this._model);
    }

    recordSlim(slimModel) {
        if(slimModel){
            this._slimModelBat = slimModel.clone();
        }else{
            this._slimModelBat = null;
        }
       
    }

    rollbackSlim() {
        this._slimModel = this._slimModelBat;
    }

    onControlsChange = () => {
        if (this._controlsChangeCallBack) this._controlsChangeCallBack()
    }


    onControlsEnd = () => {
        if (this._controlsEndCallBack) this._controlsEndCallBack()
    }


    initLights = async (exr_url) => {
        // await this.initLightExr2(exr_url);
        this.initRoom();
    }

    onWindowResize = () => {
        const { clientWidth, clientHeight } = this.dom;

        this.camera.aspect = clientWidth / clientHeight;
        this.camera.updateProjectionMatrix();

        this.renderer.setSize(clientWidth, clientHeight);
    }

    dispose() {
        window.removeEventListener('resize', this.onWindowResize);

        this.disposeHelper();

        if (this._model) this.delModel();

        this._dirLight?.shadow?.map?.dispose();
        this.mainDirLight?.shadow?.map?.dispose();

        this._ground?._dispose();

        this.scene.clear();

        this._event.dispose();

        this.controls?.removeEventListener('change', this.onControlsChange);
        this.controls?.removeEventListener('end', this.onControlsEnd);

        Cache.clear();

        setTimeout(() => {
            this.stopRender();

            this.renderer.dispose();

            this.renderer.domElement = null;

            console.log("renderer info => ", this.renderer.info);
        });

    }

    //#region init 方法
    //--------

    initShadowLight() {
        const lightScalar = 30;
        this._dirLight = new DirectionalLight(0xffffff, 2.5);
        this._dirLight.position.set(0, 1, 0).normalize();
        this._dirLight.position.multiplyScalar(lightScalar);
        this.scene.add(this._dirLight);

        this._dirLight.castShadow = true;

        this._dirLight.shadow.mapSize.width = 1024;
        this._dirLight.shadow.mapSize.height = 1024;

        const d = 20;

        this._dirLight.shadow.camera.left = -d;
        this._dirLight.shadow.camera.right = d;
        this._dirLight.shadow.camera.top = d;
        this._dirLight.shadow.camera.bottom = -d;

        this._dirLight.shadow.camera.far = 35;
        this._dirLight.shadow.bias = -0.01;
    }

    initLight() {
        const ambient = new AmbientLight(0xf5f5f5, 2); //环境光
        this.scene.add(ambient);

        const directLights = [
            { vec: new Vector3(1, 0, 0), intensity: 1.5 }, // 右
            { vec: new Vector3(-1, 0, 0), intensity: 1.5 },// 左
            // { vec: new Vector3(0, 1, 0), intensity: 1 }, // 上
            { vec: new Vector3(0, -1, 0), intensity: 1 },// 下
            { vec: new Vector3(0, 0, 1), intensity: 1.8 },// 前
            { vec: new Vector3(0, 0, -1), intensity: 1.8 },// 后
        ]
        directLights.forEach(dir => {
            const directionalLight = new DirectionalLight(0xffffff, dir.intensity); // 白色光，强度为0.5
            directionalLight.position.copy(dir.vec).normalize(); // 设置光源位置
            this.scene.add(directionalLight);
        });
    };

    initShadowLight2() {
        const lightScalar = 30;
        this._dirLight = new DirectionalLight(0xffffff, 1);
        this._dirLight.position.set(0, 1, 0).normalize();
        this._dirLight.position.multiplyScalar(lightScalar);
        this.scene.add(this._dirLight);

        this._dirLight.castShadow = true;

        this._dirLight.shadow.mapSize.width = 1024;
        this._dirLight.shadow.mapSize.height = 1024;

        const d = 20;

        this._dirLight.shadow.camera.left = -d;
        this._dirLight.shadow.camera.right = d;
        this._dirLight.shadow.camera.top = d;
        this._dirLight.shadow.camera.bottom = -d;

        this._dirLight.shadow.camera.far = 35;
        this._dirLight.shadow.bias = -0.01;
    }

    initLight2() {
        const ambient = new AmbientLight(0xffffff, 1); //环境光
        this.scene.add(ambient);

        const directLights = [
            { vec: new Vector3(1, 0, 0), intensity: 0.5 }, // 右
            { vec: new Vector3(-1, 0, 0), intensity: 0.5 },// 左
            // { vec: new Vector3(0, 1, 0), intensity: 1 }, // 上
            { vec: new Vector3(0, -1, 0), intensity: 0.3 },// 下
            { vec: new Vector3(0, 0, 1), intensity: 0.7 },// 前
            { vec: new Vector3(0, 0, -1), intensity: 0.7 },// 后
        ]

        directLights.forEach(dir => {
            const directionalLight = new DirectionalLight(0xffffff, dir.intensity); // 白色光
            directionalLight.position.copy(dir.vec).normalize(); // 设置光源位置
            this.scene.add(directionalLight);
        });
    };

    async initLightExr2(exr_url) {
        const ambient = new AmbientLight(0xffffff); //环境光
        ambient.intensity = 1;
        this.scene.add(ambient);

        const lightScalar = 30;
        this._dirLight = new DirectionalLight(0xffffff, 0.75);
        this._dirLight.position.set(0, 1, 0);
        this._dirLight.position.multiplyScalar(lightScalar);
        this.scene.add(this._dirLight);

        this._dirLight.castShadow = true;

        this._dirLight.shadow.mapSize.width = 1024;
        this._dirLight.shadow.mapSize.height = 1024;

        const d = 50;

        this._dirLight.shadow.camera.left = -d;
        this._dirLight.shadow.camera.right = d;
        this._dirLight.shadow.camera.top = d;
        this._dirLight.shadow.camera.bottom = -d;

        const loader = new MyEXRLoader();

        const texture = await loader.load(exr_url);

        texture.mapping = EquirectangularReflectionMapping;

        // this.scene.background = texture;
        this.scene.environment = texture;
        this.scene.environmentIntensity = 0.5;
    }

    initRoom() {

        this.ambient = new AmbientLight(0xffffff, 0.7);
        this.scene.add(this.ambient);

        let pmremGenerator = new PMREMGenerator(this.renderer);
        this.scene.environment = pmremGenerator.fromScene(new RoomEnvironment(), 0.04).texture;
        this.scene.environmentIntensity = 0.5;

        pmremGenerator.dispose();
        pmremGenerator = null;

        const lightScalar = 30;

        /** 主光源 */
        this.mainDirLight = new DirectionalLight(0xffffff, 0.001);
        this.scene.add(this.mainDirLight);
        this.mainDirLight.position.set(-0, 1, 0);
        this.mainDirLight.position.multiplyScalar(lightScalar);
        this.mainDirLight.castShadow = true;
        this.mainDirLight.shadow.mapSize.width = 1024;
        this.mainDirLight.shadow.mapSize.height = 1024;
        const d = 10;
        this.mainDirLight.shadow.camera.left = -d;
        this.mainDirLight.shadow.camera.right = d;
        this.mainDirLight.shadow.camera.top = d;
        this.mainDirLight.shadow.camera.bottom = -d;
        this.mainDirLight.shadow.bias = -0.0001;     // 0.0001

    }


    initGround() {
        const groundGeo = new CircleGeometry(5, 32);
        const groundMat = new ShadowMaterial();
        groundMat.opacity = 0.3;

        this._ground = new Mesh(groundGeo, groundMat);
        this._ground.rotation.x = -Math.PI / 2;
        this._ground.receiveShadow = true;
        this._ground._dispose = () => {
            groundGeo.dispose();
            groundMat.dispose();
        };
        this._ground.name ="ground";
        this.scene.add(this._ground);
    };


    startRender() {
        this.renderer.setAnimationLoop(this.animate);
    };

    stopRender() {
        this.renderer.setAnimationLoop(null);
    };

    animate = () => {
        this._stats.begin();
        this.refresh();
        this._stats.end();
    };

    refresh = () => {
        if (this.controls) this.controls.update();
        // if (this.renderer && this.scene && this.camera) {
        //     this.renderer.render(this.scene, this.camera);
        // }
        if (this._event) this._event.refresh();
    };


    //--------
    //#endregion

    //#region model 方法
    //--------




    async loadModel(url) {
        console.log("🎲模型URL：", url);

        let res;

        await this.gltfLoader.load(url)
            .then(data => { res = data; })
            .catch(err => {
                res = false;
            });

        if (!res) {
            return false;
        }

        const { scene: model } = res;

        model.traverse(x => {
            if (x.isMesh) {

                x.castShadow = true;

                x.geometry.computeVertexNormals();

                x.material = x.material.clone();

                x.material.color.set(this._whiteModelColor);
                x.material.emissive.set(0x000000);
                x.material.side = DoubleSide;
                x.material.depthTest = true;
                x.material.depthWrite = true;

                /** 半透明后面渲染 */
                if (x.material.opacity < 1) {
                    x.renderOrder = 2;
                }
                else {
                    x.renderOrder = 1;
                }

                if (x.material.map) {
                    x.material.map.generateMipmaps = true; // 开启MipMap过滤
                    x.material.map.minFilter = LinearMipMapLinearFilter;
                    x.material.map.magFilter = LinearFilter;
                    x.material.map.anisotropy =
                        this.renderer.capabilities.getMaxAnisotropy();
                }


                /** scale 矩阵变换{1,1,1} */
                // this.resetScale(x);
            }
        })

        this._oriModel = model;

        if(this._model) this._model.removeFromParent();
        
        this._model = this._oriModel;

        this.scene.add(this._model);

        this.focuModel(this._model);

        return true;
    };

    async loadModelSlim(file){
        console.log("🎲减面模型URL：", file);

        let res;

        await this.gltfLoader.load(file)
            .then(data => { res = data; })
            .catch(err => {
                res = false;
            });

        if (!res) {
            return res;
        }

        const { scene: model } = res;

        model.traverse(x => {
            if (x.isMesh) {

                x.castShadow = true;

                x.geometry.computeVertexNormals();

                x.material = x.material.clone();

                x.material.color.set(this._whiteModelColor);
                x.material.emissive.set(0x000000);
                x.material.side = DoubleSide;
                x.material.depthTest = true;
                x.material.depthWrite = true;

                /** 半透明后面渲染 */
                if (x.material.opacity < 1) {
                    x.renderOrder = 2;
                }
                else {
                    x.renderOrder = 1;
                }

                if (x.material.map) {
                    x.material.map.generateMipmaps = true; // 开启MipMap过滤
                    x.material.map.minFilter = LinearMipMapLinearFilter;
                    x.material.map.magFilter = LinearFilter;
                    x.material.map.anisotropy =
                        this.renderer.capabilities.getMaxAnisotropy();
                }


                /** scale 矩阵变换{1,1,1} */
                // this.resetScale(x);
            }
        })

        this._slimModel = model;

        if(this._model) this._model.removeFromParent();

        this._model = model;

        this.scene.add(this._model);

        this.focuModel(this._model);

        return model;
    }

    resetScale(x) {
        const geometry = x.geometry;
        const scale = x.scale;

        // 如果缩放不是 {1,1,1}，则修改顶点数据
        if (scale.x !== 1 || scale.y !== 1 || scale.z !== 1) {
            // 获取顶点数据
            const position = geometry.attributes.position;

            // 遍历所有顶点，应用缩放
            for (let i = 0; i < position.count; i++) {
                position.setX(i, position.getX(i) * scale.x);
                position.setY(i, position.getY(i) * scale.y);
                position.setZ(i, position.getZ(i) * scale.z);
            }

            // 更新 BufferGeometry
            position.needsUpdate = true;
            geometry.computeBoundingSphere(); // 重新计算包围盒

            // 重置缩放
            x.scale.set(1, 1, 1);
        }
    };

    getModelPosition() {
        return this._model.position;
    };

    getModelToGround() {
        const center = this.getModelCenter();
        const size = this.getModelSize();

        return Math.round((center.y - size.y / 2) * 1000);
    };

    getModelCenter() {
        const center = new Vector3();
        this._box3.getCenter(center);
        return center;
    }

    getModelSize() {
        const size = new Vector3();
        this._box3.getSize(size);
        return size;
    };

    getModelScale() {
        const res = [];
        res.push({
            name: "整个模型",
            scale: this._model.scale,
        })

        this._model.traverse(x => {
            if (x.isMesh) {
                res.push({
                    name: x.name,
                    scale: x.scale,
                })
            }
        })

        return res;
    };

    getModelAttribute() {
        const res = [];

        this._model.traverse(x => {
            if (x.isMesh) {
                res.push({
                    name: x.name,
                    attributes: Object.keys(x.geometry.attributes),
                })
            }
        })

        return res;

    };

    // async loadDrc(url) {
    //     const dracoLoader = new DRACOLoader();
    //     dracoLoader.setDecoderPath('/draco/');
    //     dracoLoader.setDecoderConfig({ type: 'js' });

    //     dracoLoader.load("https://oss.abangmi.com/images/1/2025/07/table.drc", function (geometry) {

    //         geometry.computeVertexNormals();

    //         const material = new MeshStandardMaterial({ color: 0xa5a5a5 });
    //         const mesh = new Mesh(geometry, material);
    //         mesh.castShadow = true;
    //         // mesh.receiveShadow = true;
    //         this.scene.add(mesh);

    //         // Release decoder resources.
    //         dracoLoader.dispose();

    //     });
    // }


    loadModelByFile(file) {
        console.log("🎲模型file：", file);
      
        return new Promise((resolve, reject) => {
            let url;
            try{
                const reader = new FileReader();
                reader.onload = async (event) => {
                    const contents = event.target.result; // 获取文件内容
                    const blob = new Blob([contents], { type: 'application/octet-stream' });
                    url = URL.createObjectURL(blob); // 创建文件
                    const res = await this.loadModel(url);
                    // throw new Error("123")
                    resolve(res)
                }
                reader.readAsArrayBuffer(file);
            }catch{
                resolve(false)
            }finally{
                URL.revokeObjectURL(url);
                console.log('loadModelByFile URL 已释放');
            }
           
        })

    };

    loadModelSlimByFile(file) {
        return new Promise((resolve, reject) => {
            let url;
            try{
                const reader = new FileReader();
                reader.onload = async (event) => {
                    const contents = event.target.result; // 获取文件内容
                    const blob = new Blob([contents], { type: 'application/octet-stream' });
                    url = URL.createObjectURL(blob); // 创建文件
                    const res = await this.loadModelSlim(url);
                    resolve(res)
                }
                reader.readAsArrayBuffer(file);
            }catch{
                resolve(false)
            }finally{
                URL.revokeObjectURL(url);
                console.log('loadModelByFile URL 已释放');
            }
           
        })

    };

    focuModel(model) {
        const center = new Vector3();
        this._box3.setFromObject(model);
        this._box3.getCenter(center);

        const size = new Vector3();
        const dis = this._box3.getSize(size).length() * 1.1;

        const direct = new Vector3();
        const dir = direct.subVectors(this.camera.position, this.controls.target).normalize();

        this.controls.target.copy(center);
        this.camera.position.copy(center).addScaledVector(dir, dis);
    }

    getObjects() {
        const res = [];

        if (!this._model) return res;

        this._model.traverse((child) => {
            if (child.isMesh) {
                res.push(child);
            }
        })

        this._event.setClickableObjects(res);

        return res;
    }

    delModel() {
        this.del(this._model);
        this.del(this._oriModel);
        this.del(this._slimModel);
        this.del(this._slimModelBat);

        console.log("===after delModel===")
        console.log(this.scene);

        this._event.clearShader();
    }

    del(model){
        if(model){
            this.scene.remove(model);
            model.traverse(x => {
                if (x.isMesh) {
                    x.geometry.dispose();
                    x.material.map?.dispose();
                    x.material.lightMap?.dispose();
                    x.material.dispose();
                }
            })
            model = null;
        }
    }

    delSlim(){
        this.del(this._slimModel);
    }

    getObjectFacesByName(name) {
        let faces = 0;
        this._model.traverse((child) => {
            if (child.isMesh) {
                if (child.name == name) {
                    const { geometry } = child;
                    if (geometry.index) {
                        // 有索引的几何体
                        faces += geometry.index.count / 3;
                    } else {
                        // 无索引的几何体
                        faces += geometry.attributes.position.count / 3;
                    }
                }
            }
        })
        return faces;
    }

    getObjectByName(name) {
        let mesh;
        this._model.traverse((child) => {
            if (child.name == name) {
                mesh = child;
            }
        })
        return mesh;
    }

    // name,allFaces,rate,retainFaces
    async slimObject(object) {

        const { name, rate } = object;

        const mesh = this.getObjectByName(name);

        const geometry = mesh._oriGeometry || mesh.geometry;

        const material = mesh.material;

        let slimGeo = geometry.clone();

        const points = geometry.attributes.position.count;

        // 至少保留3个顶点
        const removeCount = Math.min(points - 3, Math.floor(points * rate));

        if (removeCount > 0) {

            // 合并顶点，减少重复
            const mergedGeometry = mergeVertices(slimGeo);

            slimGeo = await this.modifier.modifyAsync(mergedGeometry, removeCount);

            slimGeo.computeVertexNormals();

        }

        const slimMesh = new Mesh(slimGeo, material);

        slimMesh._oriGeometry = geometry;
        slimMesh.name = mesh.name;
        slimMesh.position.copy(mesh.position);
        slimMesh.rotation.copy(mesh.rotation);
        slimMesh.castShadow = true;

        const parent = mesh.parent;
        mesh.removeFromParent();
        mesh.geometry.dispose();
        mesh.material.map?.dispose();
        mesh.material.dispose();
        parent.add(slimMesh);

        object.retainFaces = this.getObjectFacesByName(name);

        // console.log("slim:", name, rate)
    }

    //--------
    //#endregion

    //#region view
    //--------

    resetView() {
        this.camera.position.set(this.initCameraPoi.x, this.initCameraPoi.y, this.initCameraPoi.z);
        this.controls.target.set(0, 0, 0);
    }

    //--------
    //#endregion

    //#region 外发光
    //--------
    setHoverNames(names) {
        const meshs = this.findAllMeshByNames(names);
        this._event.hoverObjects(meshs);

        // const outlineMaterial = new MeshBasicMaterial({ color: 0xffffff, side: BackSide });
        // const outlineMesh = new Mesh(objects[0].geometry, outlineMaterial);
        // outlineMesh.position.set(objects[0].position.x, objects[0].position.y, objects[0].position.z);
        // outlineMesh.scale.set(1.05, 1.05, 1.05); // 稍微放大以形成描边效果
        // this.scene.add(outlineMesh);
    }

    setSelectNames(names) {
        const meshs = this.findAllMeshByNames(names);
        this._event.selectObjects(meshs);
    }
    //--------
    //#endregion

    //#region 操作mesh
    //--------
    setClickCallback(callBack) {
        this._event.setClickCallback(callBack);
    }

    setControlsChangeCallback(callBack) {
        this._controlsChangeCallBack = callBack; // 保存回调函数的引用，以便后续使用
    }

    setControlsEndCallback(callBack) {
        this._controlsEndCallBack = callBack; // 保存回调函数的引用，以便后续使用
    }

    hideMeshs(meshs_name) {
        const meshs = this.findAllMeshByNames(meshs_name);
        meshs.map(x => x.material.visible = false);
        this._event.hideShader();
    }

    showMeshs(meshs_name) {
        const meshs = this.findAllMeshByNames(meshs_name);
        meshs.map(x => x.material.visible = true);
        this._event.showShader();
    }

    async setPartColor(meshs_name, color) {
        if (!color || Array.isArray(color)) return;
        const texture = await this._tex.product(color);
        const meshs = this.findAllMeshByNames(meshs_name);
        meshs.map(x => {
            x.material.map = texture;
            x.material.lightMap = texture;
            x.material.color.set(0xffffff);
        });
    }


    clearPartColor(meshs_name) {
        const meshs = this.findAllMeshByNames(meshs_name);
        meshs.map(x => {
            x.material.map = null;
            x.material.lightMap = null;
            x.material.color.set(this._whiteModelColor);
        });
    }

    setPartColorDetail(meshs_name, color) {
        if (!color || Array.isArray(color)) return;
        const meshs = this.findAllMeshByNames(meshs_name);
        meshs.map(x => {
            x.material.roughness = color.roughness;
            x.material.metalness = color.metalness;
            x.material.opacity = color.opacity;
            x.material.transparent = true;

            /** 半透明后面渲染 */
            if (x.material.opacity < 1) {
                x.renderOrder = 2;
            } else {
                x.renderOrder = 1;
            }
        });
    }

    clearPartColorDetail(meshs_name) {
        const meshs = this.findAllMeshByNames(meshs_name);
        meshs.map(x => {
            x.material.roughness = 0;
            x.material.metalness = 0;
            x.material.opacity = 1;
            x.material.transparent = false;
        });
    }

    partNeedUpdate(meshs_name) {
        const meshs = this.findAllMeshByNames(meshs_name);
        meshs.map(x => {
            x.material.needsUpdate = true;
        });
    }

    setMapDetail(part) {
        if (!part) return;

        const { meshs_name, map_param } = part;

        const { angle, offset, scale } = map_param;
        const rad = angle * Math.PI / 180;

        const meshs = this.findAllMeshByNames(meshs_name);

        meshs.map(x => {
            const { map } = x.material;
            if (map) {
                map.offset.set(offset.x / 100, offset.y / 100);
                map.rotation = rad;
                map.repeat.set(100 / scale.x, 100 / scale.y);
            }
        });
    }
    //--------
    //#endregion

    //#region export
    //--------

    async getBuildData(d3ModelData) {
        const buildModel = this.createBuildModel(d3ModelData);

        /** 导出glb数据 */
        const data = await this.getModelData(buildModel);
        return new Blob([data], { type: 'application/octet-stream' });

        /** 导出drc数组 */
        // const arr = this.getModelDataDRACO(buildModel);
        // return arr;
    }

    async getSlimBuildData(d3ModelData) {
        if(!this._slimModel) return;
        this._model = this._slimModel;
        return await this.getBuildData(d3ModelData);
    }

    async getBuildModel(d3ModelData) {
        const buildModel = this.createBuildModel(d3ModelData);

        /** 导出glb test */
        // this.scene.remove(this._model);
        // this.scene.add(buildModel);

        /** 下载glb */
        // this.exportGLTF(buildModel);

        /** 下载drc */
        this.exportDRACO(buildModel);
    }

    createBuildModel(d3ModelData) {
        const removeList = [];
        const mergeList = [];

        const buildModel = this._model.clone();

        buildModel.traverse(x => {

            if (x.isMesh) {

                removeList.push(x);

                const belongPart = d3ModelData.model_param.find(part => part.meshs_name.includes(x.name));

                if (belongPart) {
                    let existMerge = mergeList.find(x => x.name == belongPart.name);

                    if (!existMerge) {
                        existMerge = { name: belongPart.name, meshs: [] };
                        mergeList.push(existMerge);
                    }

                    existMerge.meshs.push(x);
                }

                // const totalVertexCount = x.geometry.attributes.position.count;
                // console.log(x.name, totalVertexCount)
            }
        })


        for (let mesh of removeList) {
            mesh.parent.remove(mesh);
        }

        for (let merge of mergeList) {
            const geometries = merge.meshs.map(m => {
                const geometry = m.geometry.clone();
                geometry.applyMatrix4(m.matrixWorld);
                geometry.name = m.name;
                return geometry;
            });

            const mergeGeometry = BufferGeometryUtils.mergeGeometries(geometries);

            // if (!mergeGeometry) {
            //     console.error("🎲===合并模型失败===")
            // }

            const mergedMaterial = merge.meshs[0].material;
            const mergedMesh = new Mesh(mergeGeometry, mergedMaterial);
            mergedMesh.name = merge.name;
            buildModel.add(mergedMesh);
        }

        return buildModel;
    }

    async getModelData(input, isBinary = true) {

        return new Promise((resolve, reject) => {

            input.traverse((child) => {
                if (child.isMesh) {
                    child.geometry.computeVertexNormals();
                    child.geometry.normalizeNormals();
                    child.material = this.exportMat.clone();
                    child.material.side = DoubleSide;
                    child.material.depthWrite = true;  // 通常透明材质设为false，但不透明应该为true
                    child.material.depthTest = true;
                }
            });

            const params = {
                trs: false,
                onlyVisible: true,
                binary: isBinary,
                maxTextureSize: 4096,
            };

            const options = {
                trs: params.trs,
                onlyVisible: params.onlyVisible,
                binary: params.binary,
                maxTextureSize: params.maxTextureSize
            };

            this.gltfExporter.parse(
                input,
                (result) => {
                    if (result instanceof ArrayBuffer) {
                        resolve(result);

                    } else {
                        const output = JSON.stringify(result, null, 2);
                        resolve(output);
                    }

                },
                function (error) {
                    reject(error)
                    console.error('An error happened during parsing', error);

                },
                options
            );
        })


    }

    getModelDataDRACO(input) {
        const options = {
            compressionLevel: 7,       // 压缩级别 (1-10)
            quantizePosition: 14,      // 位置量化位数
            quantizeNormal: 10,        // 法线量化位数
            quantizeColor: 8,          // 颜色量化位数
            quantizeTexcoord: 12,      // UV坐标量化位数
            exportColor: true,         // 是否导出颜色
            exportNormals: true,       // 是否导出发线
            exportUvs: true            // 是否导出UV坐标
        };

        const resArr = [];
        input.traverse(x => {
            if (x.isMesh) {
                const dracoData = this.dracoExporter.parse(x, options);
                const blob = new Blob([dracoData], { type: 'application/octet-stream' });
                const name = x.name;
                resArr.push({ name, blob });
            }
        })

        return resArr;
    }

    exportGLTF(input) {

        input.traverse((child) => {
            if (child.isMesh) {
                child.geometry.computeVertexNormals();
                child.geometry.normalizeNormals();
                child.material = this.exportMat.clone();
                child.material.side = DoubleSide;
                child.material.depthWrite = true;  // 通常透明材质设为false，但不透明应该为true
                child.material.depthTest = true;
            }
        });

        const params = {
            trs: false,
            onlyVisible: true,
            binary: true,
            maxTextureSize: 4096,
            forceIndices: false,
            forcePowerOfTwoTextures: false,
            includeCustomExtensions: false,
        };

        const options = {
            trs: params.trs,
            onlyVisible: params.onlyVisible,
            binary: params.binary,
            maxTextureSize: params.maxTextureSize,
            forceIndices: params.forceIndices,
            forcePowerOfTwoTextures: params.forcePowerOfTwoTextures,
            includeCustomExtensions: params.includeCustomExtensions,
        };

        this.gltfExporter.parse(
            input,
            (result) => {

                if (result instanceof ArrayBuffer) {

                    this.saveArrayBuffer(result, 'scene1.glb');

                } else {

                    const output = JSON.stringify(result, null, 2);

                    this.saveString(output, 'scene.gltf');

                }

            },
            function (error) {

                console.error('An error happened during parsing', error);

            },
            options
        );

    }

    exportDRACO(input) {

        // 导出选项
        const options = {
            compressionLevel: 7,       // 压缩级别 (1-10)
            quantizePosition: 14,      // 位置量化位数
            quantizeNormal: 10,        // 法线量化位数
            quantizeColor: 8,          // 颜色量化位数
            quantizeTexcoord: 12,      // UV坐标量化位数
            exportColor: true,         // 是否导出颜色
            exportNormals: true,       // 是否导出发线
            exportUvs: true            // 是否导出UV坐标
        };

        // 执行导出
        input.traverse(x => {
            if (x.isMesh) {
                const dracoData = this.dracoExporter.parse(x, options);

                // 保存为文件
                function downloadFile(data, filename) {
                    const blob = new Blob([data], { type: 'application/octet-stream' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = filename;
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href); // 释放 URL 对象
                }

                downloadFile(dracoData, 'part.drc');
            }
        })
    }


    saveString(text, filename) {

        this.save(new Blob([text], { type: 'text/plain' }), filename);

    }


    saveArrayBuffer(buffer, filename) {

        this.save(new Blob([buffer], { type: 'application/octet-stream' }), filename);

    }

    save(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url
        a.download = filename; // 设置下载文件名
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url); // 释放 URL 对象
    }
    //--------
    //#endregion

    //#region tools
    //--------
    setClarity(clarity) {
        this._clarity = clarity;
        this.renderer.setPixelRatio(window.devicePixelRatio * this._clarity);
    }

    /** 高精度的渲染目标 */
    setRenderTarget(isOpen) {
        if (isOpen) {
            const { clientWidth, clientHeight } = this.dom;
            const renderTarget = new WebGLRenderTarget(clientWidth, clientHeight, {
                samples: 4, // 多重采样抗锯齿
            });
            this.renderer.setRenderTarget(renderTarget);
        }
        else {
            this.renderer.setRenderTarget(null);
        }
    }

    setCameraFov(fov) {
        this.camera.fov = fov; // 设置新的 FOV
        this.camera.updateProjectionMatrix(); // 更新投影矩阵
    }

    stat(dom) {
        dom.appendChild(this._stats.dom);
    }

    findAllMeshByNames(names) {
        const resMesh = [];

        if (!this._model) return resMesh;

        this._model.traverse(x => {
            if (x.isMesh && names.includes(x.name)) {
                resMesh.push(x);
            }
        })
        return resMesh;
    }
    //--------
    //#endregion

    //#region boxHelper
    //--------
    disposeBoxHelper(){
        if(this.boxHelper){
            this.boxHelper.removeFromParent();
            this.boxHelper.geometry.dispose();
            this.boxHelper.material.dispose();
            this.boxHelper = null;
        }
    }

    showBoxHelper(){
        this.disposeBoxHelper();
        this.boxHelper = new BoxHelper(this._model, 0x00ff00);
        this.scene.add(this.boxHelper);
    }
    //--------
    //#endregion

    


    createUUID(){
        return generateUUID();
    }

    getVersion(){
        return "v:1.2.1"
    }

}