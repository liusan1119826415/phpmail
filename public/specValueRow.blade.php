<!-- 规格值-树形节点-UI组件 -->
<template id="spec-value-row">
  <div class="flex-column">
    <!-- 显示同级 -->
    <div :style="{ marginLeft: computedMarginLeft + 'px' }" class="flex-row">
      <!-- 当前商品规格行：同级的输入框 -->
      <div
        v-for="(node, index) in list"
        :key="node.id"
        class="h-form-spec-value"
        draggable="true"
        @dragstart="onDragStart(index)"
        @dragover.prevent="onDragOver(index)"
        @drop="onDrop(index)"
      >
        <el-input
          v-model="node.title"
          @change="onChange(node, index)"
          @focus="onFocus(node, index)"
          :class="{ 'is-active': index === selectedIndex }"
          style="width:150px;"
          placeholder="请输入"
        ></el-input>
        <!-- 删除（顶级仅1个时隐藏） -->
        <i
          v-if="!(depth === 1 && list.length === 1)"
          @click="$emit('remove', list, index)"
          class="el-icon-circle-close h-icon-circle-close"
          ></i>
        <!-- 添加子级（仅深度<3显示） -->
        <i
          v-if="depth < 3"
          @click="!addDisabled && onAddChild(node, index)"
          :class="{ 'is-disabled': addDisabled }"
          class="el-icon-circle-plus h-icon-circle-plus"
        ></i>
        <!-- 拖拽显示 -->
        <div v-if="index === dragOverIndex && dragIndex !== dragOverIndex" class="ydivider"></div>
      </div>
      <!-- 行尾：只出现一个“添加规格值”按钮（添加同级） -->
      <div style="padding-bottom:10px;">
        <el-button
          :disabled="addDisabled"
          @click="$emit('add-sibling-to-list', specIndex, list, depth)" >
          添加规格值
        </el-button>
      </div>
    </div>
    

    <!-- 仅显示“当前选中项”的子级 -->
    <div v-if="currentNode && currentNode.children && currentNode.children.length">
      <spec-value-row
        :list="currentNode.children"
        :depth="depth + 1"
        :spec-index="specIndex"
        :add-disabled="addDisabled"
        :selected-top-index="selectedTopIndex"
        :selected-path-ids="selectedPathIds"
        @blur="$emit('blur', ...arguments)"
        @add-child="$emit('add-child', ...arguments)"
        @remove="$emit('remove', ...arguments)"
        @add-sibling-to-list="$emit('add-sibling-to-list', ...arguments)"
        @on-select="$emit('on-select', ...arguments)"
      />
    </div>
    <!-- 递归：每个节点的子级（若有）渲染为下一行 -->
    <!-- <div v-for="node in list" :key="node.id + '_children'">
      <spec-value-row
        v-if="node.children && node.children.length"
        :list="node.children"
        :depth="depth + 1"
        :spec-index="specIndex"
        @blur="$emit('blur', ...arguments)"
        @add-child="$emit('add-child', ...arguments)"
        @remove="$emit('remove', ...arguments)"
        @add-sibling-to-list="$emit('add-sibling-to-list', ...arguments)"
      />
    </div> -->
  </div>
</template>

<script type="module">
    Vue.component('SpecValueRow', {
        delimiters: ['[[', ']]'],
        template: "#spec-value-row",
        props: {
          list: { type: Array, required: true }, // 同级数组（这一行）
          depth: { type: Number, required: true }, // 当前深度：1~3
          specIndex: { type: Number, required: true }, // 规格组Index透传给父）
          addDisabled: { type: Boolean, required: true }, // 从父组件传递过来的 disabled 状态
          selectedTopIndex: { type: Number, default: 0 }, //当前选中的顶级规格值索引
          selectedPathIds: { type: Array, default: () => [] }, //父传路径 ids
        },
        data() {
            return {
              selectedIndex: 0,   //选中的索引（当前同级） 默认选中第一个
              // 拖拽相关状态
              dragIndex: -1, // 起始拖拽的索引
              dragOverIndex: -1, // 当前经过的目标索引
            }
        },
        computed: {
          // “添加规格值”按钮禁用：判断这一行（list）是否存在空标题
          // addDisabled() {
          //   return this.list.some(v => !String(v.title || '').trim());
          // },
          // 计算缩进
          computedMarginLeft() {
            return (this.depth - 1) * 12 + (this.depth > 1 ? this.selectedTopIndex * 170 : 0); // 子级添加额外的缩进
          },
          // 当前选中节点（展示其子级）
          currentNode() {
            if (!Array.isArray(this.list) || this.list.length === 0) return null;
            const idx = Math.max(0, Math.min(this.selectedIndex, this.list.length - 1));
            return this.list[idx];
          }
        },
        watch: {
          // 监听同级数组 保障 selectedIndex 合法；删除/新增后若越界，自动就近调整
          list: {
            immediate: true,
            deep: true,
            handler() {
              if (!Array.isArray(this.list) || this.list.length === 0) {
                this.selectedIndex = -1;
                return;
              }
              if (this.selectedIndex < 0) this.selectedIndex = 0;
              if (this.selectedIndex >= this.list.length) {
                this.selectedIndex = this.list.length - 1;
              }
            }
          },
          // 监听父传路径 当父组件更新选中路径时，按 depth 匹配本行应选中的 id，并更新 selectedIndex
          selectedPathIds: {
            immediate: true,
            handler(val) {
              if (!Array.isArray(val) || !val.length || !Array.isArray(this.list)) return;
              const targetId = val[this.depth - 1]; // depth 从 1 起
              if (!targetId) return;
              const i = this.list.findIndex(n => String(n.id) === String(targetId));
              if (i !== -1) this.selectedIndex = i;
            }
          },
        },
        created() {},
        mounted() {},
        methods: {
          // 记录选中的索引
          selectIndex(index) {
            if (index >= 0 && index < this.list.length) {
              this.selectedIndex = index;
            }
          },
          //聚焦事件
          onFocus(node, index) {
            // console.log("onFocus");
            this.selectIndex(index); // 记录选中
            this.$emit('on-select', node, index, this.depth); // 把当前选中的“规格值节点”抛给父组件
          },
          // 添加子级
          onAddChild(node, index) {
            if (this.selectedIndex !== index) this.selectedIndex = index; // 先选中，再加子级
            this.onFocus(node, index); //聚焦
            this.$emit('add-child', this.specIndex, node);
          },
          // 值改变时触发
          onChange(node, index) {
            // 检查规格值并生成规格组合
            this.$emit('blur', this.specIndex, node, index, this.list);
            // 在规格组合生成后，聚焦当前选中的“规格值”
            // 触发父组件联动展示对应的规格组合详细信息
            setTimeout(() => {
              this.onFocus(node, index); //聚焦
            }, 500);
          },

          // 拖拽相关代码
          // 开始拖拽：记录起始索引
          onDragStart(startIndex) {
            this.dragIndex = startIndex;
          },
          // 拖拽经过：记录目标索引
          onDragOver(targetIndex) {
            this.dragOverIndex = targetIndex;
          },
          // 释放：在同级内重排, 并重算 sort, 同时保持“选中项”不丢失
          onDrop(targetIndex) {
            try {
              if (this.dragIndex === -1 || targetIndex === this.dragIndex) return;

              const from = this.dragIndex;
              const to = targetIndex;

              // 记录当前选中节点的 id，重排后用于恢复选中
              const selectedId = this.list?.[this.selectedIndex]?.id;

              // 同级数组内移动元素
              const moved = this.list.splice(from, 1)[0];
              this.list.splice(to, 0, moved);

              // 统一重排 sort
              this.list.forEach((n, i) => this.$set(n, "sort", i));

              // 恢复选中：按 id 定位新下标
              const newSel = this.list.findIndex(n => String(n.id) === String(selectedId));
              this.selectedIndex = newSel !== -1 ? newSel : Math.min(to, this.list.length - 1);

              // 【关键联动】如果是顶级行，被拖拽后需要同步父组件的 selectedTopIndex
              if (this.depth === 1) {
                const current = this.list[this.selectedIndex];
                // 复用原有 on-select 事件通道，父组件会用 index 更新 selectedTopIndex
                this.$emit('on-select', current, this.selectedIndex, this.depth);
              }
            } finally {
              this.dragIndex = -1;
              this.dragOverIndex = -1;
            }
          },
        },
    })
</script>


<style scoped lang='scss'>
.flex-column {
  display: flex;
  flex-direction: column;
  gap: 15px;
}
.flex-row {
  display: flex;
  /* flex-wrap: wrap; */
  align-items: flex-start;
}
.h-form-spec-value {
  position: relative;
  margin-right: 20px;
  padding-bottom:10px;
}
.h-icon-close {
  font-size: 16px;
  cursor:pointer;
  padding-right:10px;
}
.h-icon-circle-plus {
  position: absolute;
  right: -9px;
  bottom: 0px;
  font-size: 20px;
  color: #707070;
  cursor: pointer;
}
.h-icon-circle-plus.is-disabled {
  color: #ccc;  /* 禁用时颜色变灰 */
  cursor: not-allowed;  /* 禁用时鼠标指针变为不可点击的样式 */
}
/* 竖分割线 */
.ydivider {
  position: absolute;
  left: -10px;
  top: 0px;
  width: 1px;
  height: 40px;
  background-color: #29BA9C;
}
</style>
