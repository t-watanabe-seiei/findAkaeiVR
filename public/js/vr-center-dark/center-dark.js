/**
 * A-Frame カスタムコンポーネント: center-dark-overlay
 * 中心暗転（逆トンネルビジョン）エフェクトを実装
 * カメラの視線中心部を暗転させ、周辺部は明瞭に表示
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

// Fragment Shader（フラグメントシェーダー）- 中心暗転版（反転ロジック）
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
    
    // ★重要: alphaを反転（視野狭窄と逆）
    // これにより中心=黒、外側=透明になる
    alpha = 1.0 - alpha;
    
    // 黒色で、alphaで透明度を制御
    // 中心（innerRadius以内）は不透明（alpha=1）→ 完全な黒
    // 外側（outerRadius以上）は透明（alpha=0）→ 360度画像が見える
    gl_FragColor = vec4(0.0, 0.0, 0.0, alpha * opacity);
  }
`;

AFRAME.registerComponent('center-dark-overlay', {
  schema: {
    // 球体の物理的な半径（メートル単位）
    sphereRadius: { type: 'number', default: 0.4 },
    
    // 黒い部分の不透明度（0.0～1.0）
    opacity: { type: 'number', default: 1.0 },
    
    // 球体のセグメント数（パフォーマンスと品質のバランス）
    segments: { type: 'int', default: 48 }
  },

  /**
   * コンポーネントの初期化
   */
  init: function () {
    console.log('center-dark-overlay: 初期化開始');
    
    // VRモード開始時刻（null: VRモード未開始）
    this.startTime = null;
    
    // 暗転範囲の時間変化ステージ定義
    this.stages = [
      { time: 0,  innerRadius: 0.00, outerRadius: 0.00 },  // 0～10秒: 暗転なし
      { time: 10, innerRadius: 0.00, outerRadius: 0.00 },  // ステージ境界
      { time: 20, innerRadius: 0.11, outerRadius: 0.15 },  // 10～20秒: 視野角20度
      { time: 30, innerRadius: 0.17, outerRadius: 0.23 },  // 20～30秒: 視野角30度
      { time: 40, innerRadius: 0.22, outerRadius: 0.30 },  // 30～40秒: 視野角40度
      { time: 50, innerRadius: 0.28, outerRadius: 0.36 }   // 40～50秒: 視野角50度
    ];
    
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
    
    // カスタムシェーダーマテリアルの作成（初期値: 暗転なし）
    const material = new THREE.ShaderMaterial({
      uniforms: {
        innerRadius: { value: 0.0 },
        outerRadius: { value: 0.0 },
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
    el.setObject3D('center-dark-mesh', mesh);
    
    // メッシュへの参照を保存（後で更新できるように）
    this.mesh = mesh;
    this.geometry = geometry;
    this.material = material;
    
    // VRモード開始イベントをリッスン
    this.el.sceneEl.addEventListener('enter-vr', this.onEnterVR.bind(this));
    
    console.log('center-dark-overlay: シェーダー適用完了（動的モード）');
  },

  /**
   * VRモード開始時の処理
   */
  onEnterVR: function () {
    console.log('center-dark-overlay: VRモード開始 - タイマースタート');
    this.startTime = Date.now();
  },

  /**
   * 線形補間（lerp）関数
   */
  lerp: function(a, b, t) {
    return a + (b - a) * t;
  },

  /**
   * 毎フレームの更新処理
   */
  tick: function (time, deltaTime) {
    // VRモード未開始の場合は何もしない
    if (this.startTime === null) return;
    
    // 経過時間を秒単位で計算
    const elapsedTime = (Date.now() - this.startTime) / 1000;
    
    // 50秒でループ
    const loopTime = elapsedTime % 50;
    
    // 現在のステージと次のステージを特定
    let currentStage = null;
    let nextStage = null;
    
    for (let i = 0; i < this.stages.length - 1; i++) {
      if (loopTime >= this.stages[i].time && loopTime < this.stages[i + 1].time) {
        currentStage = this.stages[i];
        nextStage = this.stages[i + 1];
        break;
      }
    }
    
    // ステージが見つからない場合（50秒ちょうど）は最初に戻る
    if (!currentStage) {
      currentStage = this.stages[this.stages.length - 1];
      nextStage = this.stages[0];
    }
    
    // ステージ内の進行度を計算（0.0～1.0）
    const stageDuration = nextStage.time - currentStage.time;
    const stageProgress = stageDuration > 0 
      ? (loopTime - currentStage.time) / stageDuration 
      : 0;
    
    // 線形補間でパラメータを計算
    const innerRadius = this.lerp(currentStage.innerRadius, nextStage.innerRadius, stageProgress);
    const outerRadius = this.lerp(currentStage.outerRadius, nextStage.outerRadius, stageProgress);
    
    // シェーダーのUniformsを更新
    this.material.uniforms.innerRadius.value = innerRadius;
    this.material.uniforms.outerRadius.value = outerRadius;
  },

  /**
   * コンポーネントの削除
   */
  remove: function () {
    // クリーンアップ処理（必要に応じて）
    console.log('center-dark-overlay: 削除');
  }
});

console.log('center-dark.js: スクリプト読み込み完了');
