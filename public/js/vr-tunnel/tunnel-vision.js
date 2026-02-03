/**
 * A-Frame カスタムコンポーネント: tunnel-vision-overlay
 * 視野狭窄（トンネルビジョン）エフェクトを実装
 * カメラの視線中心部のみ明瞭に表示し、周辺部を暗転させる
 */

// Vertex Shader（頂点シェーダー）
const vertexShader = `
  varying vec3 vPosition;
  
  void main() {
    // 頂点位置をフラグメントシェーダーに渡す
    vPosition = position;
    
    // 標準的な変換
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
  }
`;

// Fragment Shader（フラグメントシェーダー）
const fragmentShader = `
  uniform float innerRadius;
  uniform float outerRadius;
  uniform float opacity;
  
  varying vec3 vPosition;
  
  void main() {
    // カメラからの方向ベクトル（正規化）
    vec3 direction = normalize(vPosition);
    
    // 前方向（Z軸負方向）との角度を計算
    // direction.z = cos(angle)、Z軸負方向が視線方向
    float angle = acos(-direction.z);
    
    // 正規化された距離 (0.0 = 中心, 1.0 = 外側)
    // PI (3.14159)で割ることで0～1の範囲に正規化
    float normalizedDistance = angle / 3.14159;
    
    // innerRadius～outerRadiusの範囲でスムーズに補間
    // smoothstepで滑らかなグラデーション
    float alpha = smoothstep(innerRadius, outerRadius, normalizedDistance);
    
    // 黒色で、alphaで透明度を制御
    // 中心（innerRadius以内）は透明（alpha=0）
    // 外側（outerRadius以上）は不透明（alpha=1）
    gl_FragColor = vec4(0.0, 0.0, 0.0, alpha * opacity);
  }
`;

AFRAME.registerComponent('tunnel-vision-overlay', {
  schema: {
    // 完全に透明な中心領域の半径（0.0～1.0の正規化値）
    // 視野角約30度に相当
    innerRadius: { type: 'number', default: 0.15 },
    
    // 完全に黒くなる外側領域の半径（0.0～1.0の正規化値）
    // 視野角約50度に相当
    outerRadius: { type: 'number', default: 0.35 },
    
    // 球体の物理的な半径（メートル単位）
    // カメラに近い位置に配置（0.4mで最適）
    sphereRadius: { type: 'number', default: 0.4 },
    
    // 黒い部分の不透明度（0.0～1.0）
    opacity: { type: 'number', default: 0.95 },
    
    // 球体のセグメント数（パフォーマンスと品質のバランス）
    segments: { type: 'int', default: 48 }
  },

  /**
   * コンポーネントの初期化
   */
  init: function () {
    console.log('tunnel-vision-overlay: 初期化開始');
    
    // データの取得
    const data = this.data;
    
    // Three.jsオブジェクトへの参照
    const el = this.el;
    
    // 球体ジオメトリの作成
    const geometry = new THREE.SphereGeometry(
      data.sphereRadius,  // 半径
      data.segments,      // 水平方向のセグメント数
      data.segments       // 垂直方向のセグメント数
    );
    
    // カスタムシェーダーマテリアルの作成
    const material = new THREE.ShaderMaterial({
      uniforms: {
        innerRadius: { value: data.innerRadius },
        outerRadius: { value: data.outerRadius },
        opacity: { value: data.opacity }
      },
      vertexShader: vertexShader,
      fragmentShader: fragmentShader,
      transparent: true,
      side: THREE.BackSide,  // 球体の内側を描画
      depthWrite: false,     // 深度バッファを書き込まない
      depthTest: false       // 深度テストをスキップ（常に最前面）
    });
    
    // メッシュの作成
    const mesh = new THREE.Mesh(geometry, material);
    
    // エンティティのObject3Dに追加
    el.setObject3D('tunnel-vision-mesh', mesh);
    
    // メッシュへの参照を保存（後で更新できるように）
    this.mesh = mesh;
    this.geometry = geometry;
    this.material = material;
    
    console.log('tunnel-vision-overlay: シェーダー適用完了', {
      radius: data.sphereRadius,
      segments: data.segments,
      innerRadius: data.innerRadius,
      outerRadius: data.outerRadius
    });
  },

  /**
   * コンポーネントの更新（プロパティ変更時）
   */
  update: function (oldData) {
    // マテリアルが存在しない場合は何もしない（初期化前）
    if (!this.material) return;
    
    const data = this.data;
    
    // Uniformsの更新
    this.material.uniforms.innerRadius.value = data.innerRadius;
    this.material.uniforms.outerRadius.value = data.outerRadius;
    this.material.uniforms.opacity.value = data.opacity;
    
    console.log('tunnel-vision-overlay: パラメータ更新', {
      innerRadius: data.innerRadius,
      outerRadius: data.outerRadius,
      opacity: data.opacity
    });
  },

  /**
   * コンポーネントの削除
   */
  remove: function () {
    // クリーンアップ処理（必要に応じて）
    console.log('tunnel-vision-overlay: 削除');
  }
});

console.log('tunnel-vision.js: スクリプト読み込み完了');
