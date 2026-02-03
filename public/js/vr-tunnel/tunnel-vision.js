/**
 * A-Frame カスタムコンポーネント: tunnel-vision-overlay
 * 視野狭窄（トンネルビジョン）エフェクトを実装
 * カメラの視線中心部のみ明瞭に表示し、周辺部を暗転させる
 */

/**
 * A-Frame カスタムコンポーネント: auto-enter-vr
 * ページロード時に自動的にVRモードに切り替える
 * - VRデバイス（Pico4など）: オーバーレイを非表示にして自動VRモード起動
 * - デスクトップ: オーバーレイクリックでフルスクリーン表示
 */
AFRAME.registerComponent('auto-enter-vr', {
  init: function () {
    const sceneEl = this.el;
    const overlay = document.getElementById('vr-start-overlay');
    
    console.log('[tunnel-vision] auto-enter-vr: 初期化開始');
    
    // WebXR APIでVRデバイスをチェック
    if (navigator.xr && navigator.xr.isSessionSupported) {
      navigator.xr.isSessionSupported('immersive-vr').then((supported) => {
        if (supported) {
          // VRデバイス検出 - 自動的にVRモードに切り替え
          console.log('[tunnel-vision] VRデバイス検出 - 自動VRモード起動');
          overlay.classList.add('hidden');
          
          // 1秒待ってからVRモードに入る
          setTimeout(() => {
            sceneEl.enterVR();
          }, 1000);
        } else {
          // デスクトップ環境 - オーバーレイ表示
          console.log('[tunnel-vision] デスクトップ環境 - オーバーレイ表示');
          setupDesktopMode();
        }
      }).catch((err) => {
        console.error('[tunnel-vision] WebXR チェックエラー:', err);
        setupDesktopMode();
      });
    } else {
      // WebXR非対応 - オーバーレイ表示
      console.log('[tunnel-vision] WebXR非対応 - オーバーレイ表示');
      setupDesktopMode();
    }
    
    function setupDesktopMode() {
      overlay.addEventListener('click', () => {
        console.log('[tunnel-vision] オーバーレイクリック - フルスクリーン表示');
        overlay.classList.add('hidden');
        
        // フルスクリーン表示
        if (document.documentElement.requestFullscreen) {
          document.documentElement.requestFullscreen();
        }
      });
    }
  }
});

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
    // 初期状態は1.0（視野狭窄なし）
    innerRadius: { type: 'number', default: 1.0 },
    
    // 完全に黒くなる外側領域の半径（0.0～1.0の正規化値）
    // innerRadius + 0.20の固定オフセット
    outerRadius: { type: 'number', default: 1.2 },
    
    // 球体の物理的な半径（メートル単位）
    // カメラに近い位置に配置（0.4mで最適）
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
    console.log('[tunnel-vision] 初期化開始');
    
    // データの取得
    const data = this.data;
    
    // Three.jsオブジェクトへの参照
    const el = this.el;
    
    // タイマー関連の状態
    this.startTime = null;
    this.isVRMode = false;
    this.cycleDuration = 50000; // 50秒サイクル
    
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
    
    // VRモードのイベントリスナー
    const sceneEl = this.el.sceneEl;
    
    this.onEnterVR = this.onEnterVR.bind(this);
    this.onExitVR = this.onExitVR.bind(this);
    
    sceneEl.addEventListener('enter-vr', this.onEnterVR);
    sceneEl.addEventListener('exit-vr', this.onExitVR);
    
    console.log('[tunnel-vision] シェーダー適用完了', {
      radius: data.sphereRadius,
      segments: data.segments,
      innerRadius: data.innerRadius,
      outerRadius: data.outerRadius
    });
  },

  /**
   * VRモード開始時の処理
   */
  onEnterVR: function () {
    console.log('[tunnel-vision] VRモード開始 - タイマースタート');
    this.startTime = Date.now();
    this.isVRMode = true;
  },

  /**
   * VRモード終了時の処理
   */
  onExitVR: function () {
    console.log('[tunnel-vision] VRモード終了 - タイマー停止');
    this.isVRMode = false;
    this.startTime = null;
  },

  /**
   * 経過時間からinnerRadiusを計算
   */
  getInnerRadiusForTime: function (elapsedMs) {
    const elapsed = elapsedMs % this.cycleDuration; // 50秒でループ
    const sec = elapsed / 1000;
    
    if (sec < 5) {
      // 0-5秒: 1.0を維持（視野狭窄なし）
      return 1.0;
    } else if (sec < 15) {
      // 5-15秒: 1.0から0.175へ線形補間
      const t = (sec - 5) / 10;
      return this.lerp(1.0, 0.175, t);
    } else if (sec < 25) {
      // 15-25秒: 0.175から0.125へ線形補間
      const t = (sec - 15) / 10;
      return this.lerp(0.175, 0.125, t);
    } else if (sec < 35) {
      // 25-35秒: 0.125から0.075へ線形補間
      const t = (sec - 25) / 10;
      return this.lerp(0.125, 0.075, t);
    } else if (sec < 45) {
      // 35-45秒: 0.075から0.025へ線形補間
      const t = (sec - 35) / 10;
      return this.lerp(0.075, 0.025, t);
    } else {
      // 45-50秒: 0.025から0.01へ線形補間
      const t = (sec - 45) / 5;
      return this.lerp(0.025, 0.01, t);
    }
  },

  /**
   * 線形補間
   */
  lerp: function (a, b, t) {
    return a + (b - a) * t;
  },

  /**
   * 毎フレーム実行
   */
  tick: function () {
    // VRモード中のみ動作
    if (!this.isVRMode || !this.startTime) {
      return;
    }
    
    // 経過時間を計算
    const elapsedMs = Date.now() - this.startTime;
    
    // innerRadiusを計算
    const newInnerRadius = this.getInnerRadiusForTime(elapsedMs);
    
    // outerRadiusはinnerRadius + 0.20の固定オフセット
    const newOuterRadius = newInnerRadius + 0.20;
    
    // シェーダーのuniformsを更新
    if (this.material && this.material.uniforms) {
      this.material.uniforms.innerRadius.value = newInnerRadius;
      this.material.uniforms.outerRadius.value = newOuterRadius;
    }
  },

  /**
   * コンポーネントの更新（プロパティ変更時）
   */
  update: function (oldData) {
    // マテリアルが存在しない場合は何もしない（初期化前）
    if (!this.material) return;
    
    const data = this.data;
    
    // Uniformsの更新（手動変更時のみ、tick()で自動更新されるinnerRadius/outerRadiusは除く）
    this.material.uniforms.opacity.value = data.opacity;
    
    console.log('[tunnel-vision] パラメータ更新', {
      opacity: data.opacity
    });
  },

  /**
   * コンポーネントの削除
   */
  remove: function () {
    // イベントリスナーのクリーンアップ
    const sceneEl = this.el.sceneEl;
    if (sceneEl) {
      sceneEl.removeEventListener('enter-vr', this.onEnterVR);
      sceneEl.removeEventListener('exit-vr', this.onExitVR);
    }
    console.log('[tunnel-vision] 削除');
  }
});

console.log('[tunnel-vision] スクリプト読み込み完了');
