import { TextureLoader } from 'three'
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { EXRLoader } from 'three/addons/loaders/EXRLoader.js';

class Loader {
    #loader;
    constructor(LoaderType) {
        this.#loader = new LoaderType();
    }
    load(url) {
        return new Promise((resolve, reject) => {
            this.#loader.load(
                url,
                function (data) {
                    resolve(data);
                },
                function (xhr) {
                    let num = Math.floor((xhr.loaded / xhr.total) * 100) / 100;
                    if (num) console.info(`加载${url} :${(num * 100).toString()}%`);
                },
                function (err) {
                    console.error("加载失败：" + err)
                    reject(err);
                }
            );
        });
    }
}

class MyGLTFLoader extends Loader {
    constructor() { super(GLTFLoader); }
}

class MyTextureLoader extends Loader {
    constructor() { super(TextureLoader); }
}

class MyFBXLoader extends Loader {
    constructor() { super(GLTFLoader); }
}

class MyEXRLoader extends Loader {
    constructor() {
      super(EXRLoader);
    }
  }

export { MyGLTFLoader, MyTextureLoader, MyFBXLoader,MyEXRLoader }