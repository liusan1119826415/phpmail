import { MyTextureLoader } from "./loader.js";
import {
    Texture,
    RepeatWrapping,
    LinearMipMapLinearFilter,
    LinearFilter,
    SRGBColorSpace,
} from "three";
class Tex {
    weakMap = new WeakMap();
    loader = new MyTextureLoader();

    constructor() {
        if (!Tex.instance) {
            Tex.instance = this;
        }

        return Tex.instance;
    }

    async product(color) {

        let texture = this.weakMap.get(color);

        if (!texture) {
            texture = await this.loader.load(color.thumb);

            texture = this.#resizeTexture(texture, 512, 512);

            texture.colorSpace = SRGBColorSpace;

            texture.wrapS = texture.wrapT = RepeatWrapping;

            texture.generateMipmaps = true;
            texture.anisotropy = 16;
            texture.minFilter = LinearMipMapLinearFilter; // Mipmaps 时的过滤器
            texture.magFilter = LinearFilter; // 放大时的过滤器

            this.weakMap.set(color, texture);
        }

        return texture;
    }

    #resizeTexture(texture, width, height) {
        const canvas = document.createElement("canvas");
        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext("2d");
        ctx.drawImage(texture.image, 0, 0, width, height);

        const resizedTexture = new Texture(canvas);
        resizedTexture.needsUpdate = true; // 标记为需要更新
        return resizedTexture;
    };
}

export default Tex;