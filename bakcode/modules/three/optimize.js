import {
    Group,
    BufferGeometry,
    Float32BufferAttribute,
    Mesh,
} from "three";
import { SimplifyModifier } from 'three/addons/modifiers/SimplifyModifier';
import * as BufferGeometryUtils from 'three/addons/utils/BufferGeometryUtils.js';

export const sampleModel = (model) => {
    const limitVertexCount = 1.5 * 10000;
    const group = new Group();
    model.traverse(x => {
        if (x.isMesh) {
            const totalVertexCount = x.geometry.attributes.position.count;

            const simplifiedObj = totalVertexCount > limitVertexCount
                ? this.decimateMesh(x, limitVertexCount / totalVertexCount || 0.3)
                : x.clone();

            const totalVertexCountNew = simplifiedObj.geometry.attributes.position.count;

            console.log(x.name, totalVertexCount, totalVertexCountNew, totalVertexCountNew / totalVertexCount)

            group.add(x.clone());
        }

    })

    return group;
}

decimateGeometry = (geometry, ratio) => {
    const oldPos = geometry.attributes.position;
    const oldNorm = geometry.attributes.normal;
    const oldUV = geometry.attributes.uv;

    const newPos = [];
    const newNorm = [];
    const newUV = [];

    // 按比例跳过顶点
    for (let i = 0; i < oldPos.count; i += Math.max(1, Math.floor(1 / ratio))) {
        newPos.push(oldPos.getX(i), oldPos.getY(i), oldPos.getZ(i));
        if (oldNorm) newNorm.push(oldNorm.getX(i), oldNorm.getY(i), oldNorm.getZ(i));
        if (oldUV) newUV.push(oldUV.getX(i), oldUV.getY(i));
    }

    const newGeometry = new BufferGeometry();
    newGeometry.setAttribute('position', new Float32BufferAttribute(newPos, 3));
    if (newNorm.length) newGeometry.setAttribute('normal', new Float32BufferAttribute(newNorm, 3));
    if (newUV.length) newGeometry.setAttribute('uv', new Float32BufferAttribute(newUV, 2));

    return newGeometry;
}

const cleanGeometry = (geometry) => {
    // 合并重复顶点
    const cleanedGeometry = BufferGeometryUtils.mergeVertices(geometry);

    // 移除未使用的属性
    const usedAttributes = ['position', 'normal', 'uv']; // 根据需要调整
    for (const name in cleanedGeometry.attributes) {
        if (!usedAttributes.includes(name)) {
            cleanedGeometry.deleteAttribute(name);
        }
    }

    return cleanedGeometry;
}

const simplifyGeometry = (geometry, ratio = 0.7) => {
    const modifier = new SimplifyModifier();
    return modifier.modify(geometry, Math.floor(geometry.attributes.position.count * ratio));
}

const decimateMesh = (mesh, ratio) => {
    // const newGeometry = this.decimateGeometry(mesh.geometry, ratio);
    // const newGeometry = this.cleanGeometry(mesh.geometry);
    const newGeometry = this.simplifyGeometry(mesh.geometry);
    const newMesh = new Mesh(newGeometry, mesh.material);
    newMesh.position.copy(mesh.position);
    newMesh.rotation.copy(mesh.rotation);
    newMesh.scale.copy(mesh.scale);
    newMesh.name = mesh.name;
    return newMesh;
}