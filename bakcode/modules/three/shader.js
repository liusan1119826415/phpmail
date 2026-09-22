// 描边着色器
export const OutlineShader = (tickness, color, side) => {
    return {
        uniforms: {
            "thickness": { value: tickness },
            "color": { value: color }
        },
        vertexShader: `
          uniform float thickness;
          void main() {
            vec3 newPosition = position + normal * thickness;
            gl_Position = projectionMatrix * modelViewMatrix * vec4(newPosition, 1.0);
          }
        `,
        fragmentShader: `
          uniform vec3 color;
          void main() {
            gl_FragColor = vec4(color, 1.0);
          }
        `,
        side: side
    }
}