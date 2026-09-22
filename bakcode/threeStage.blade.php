<template id="three-stage">
    <el-dialog :visible.sync="visible" :before-close="handleClose" class="three-dialog" fullscreen>
        <template slot="title">
            <div class="stage-title">
                <span class="title-text">[[d3ModelData.title]]</span>
                <el-button @click="onChangeModel"><i class="el-icon-upload el-icon--left"></i>替换模型</el-button>
                <input type="file" ref="fileInput" accept=".glb" @change="glbInputChange" style="display: none;" />
                <el-button @click="modelCheck.visible=true"><i
                        class="el-icon-tickets el-icon--left"></i>模型报告</el-button>

                <el-button :disabled="!loadResult" :class="{ 'disabled-opacity': loadResult }" @click="onModelSlim"><i
                        class="el-icon-finished el-icon--left"></i>模型减面</el-button>
            </div>
        </template>

        <div class="stage-wrapper" v-loading="loading" :class="{ disabled: !modelCheck.isPass }">
            <div class="stage-left">
                <div class="left-top">
                    <div class="left-top-msg">
                        <div>部件信息</div>
                        <div>
                            <el-button :disabled="!loadResult" type="warning" size="mini"
                                @click="clearPart">清空部件</el-button>
                            <el-button :disabled="!loadResult" type="primary" size="mini"
                                @click="newPart(true)">新建部件</el-button>
                        </div>
                    </div>
                    <div class="part-wrapper scrollable-content">

                        <draggable v-model="d3ModelData.model_param" v-bind="dragOptions" item-key="uuid">

                            <div class="part" :class="{ sel: item.uuid && selPart?.uuid == item?.uuid }"
                                v-for="(item,index) of d3ModelData.model_param" :key="'part_name_' + index"
                                @click="onSelPart(item)">

                                <el-tooltip class="item" effect="dark" :content="item.name" placement="top"
                                    :open-delay="300">
                                    <div class="part-name">
                                        <!-- <el-input v-model="item.name" v-focus placeholder="请输入部件名" @change="hasSameName"></el-input> -->
                                        [[item.name]]
                                    </div>
                                </el-tooltip>


                                <div class="funcs">

                                    <el-tooltip class="item" effect="dark" content="克隆部件" placement="top"
                                        :open-delay="300">
                                        <div class="func" @click.stop="onClonePart(item)">
                                            <i class="local-iconfont icon-copy">
                                            </i>
                                        </div>
                                    </el-tooltip>

                                    <el-tooltip class="item" effect="dark" content="当前已配置材质" placement="top"
                                        :open-delay="300">
                                        <div class="count" v-show="getMaterialCount(item)>0">
                                            [[getMaterialCount(item)]]
                                        </div>
                                    </el-tooltip>

                                    <el-tooltip class="item" effect="dark"
                                        :content="item.changeLock ? '当前可配色' : '当前不可配色'" placement="top"
                                        :open-delay="300">
                                        <div class="func" @click.stop="onchangeLock(item)">
                                            <i
                                                :class="`local-iconfont ${item.changeLock?'icon-kepeise':'icon-bukepeise'}`">
                                            </i>
                                        </div>
                                    </el-tooltip>

                                    <el-tooltip class="item" effect="dark"
                                        :content="item.visible ? '当前不可见' : '当前可见'" placement="top"
                                        :open-delay="300">
                                        <div class="func" @click.stop="onDisVisible(item)">
                                            <i
                                                :class="`local-iconfont ${item.visible?'icon-bukejiananniu':'icon-kejiananniu'}`">
                                            </i>
                                        </div>
                                    </el-tooltip>

                                    <el-tooltip class="item" effect="dark" content="删除部件" placement="top"
                                        :open-delay="300">
                                        <div class="func" @click.stop="onDeletePart(item,index)">
                                            <i class="local-iconfont icon-shanchuanniu"></i>
                                        </div>
                                    </el-tooltip>
                                </div>
                            </div>

                        </draggable>

                        <div v-if="!d3ModelData.model_param.length" class="import-part">
                            <el-button :disabled="!loadResult" v-if="baseParts.length" type="info" size="mini"
                                @click="onImportPart">导入部件</el-button>
                            <el-button :disabled="!loadResult"
                                v-if="allObjectList.length && ( !baseParts.length || d3ModelData.tabIndex == 0)"
                                type="info" size="mini" @click="onPartByObject">从对象生成</el-button>
                        </div>

                        {{-- 快捷设置 --}}
                        <div v-if="!d3ModelData.model_param.length"
                            style="margin-top:20px;display:flex;flex-direction: column; justify-content: flex-end;gap:10px">
                            <el-button :disabled="!loadResult" @click="oneKey" size="mini"
                                type="success">一键设置(一个部件包含所有对象)</el-button>
                            <el-button :disabled="!loadResult" @click="oneKey2" size="mini" type="warning"
                                style="margin-left:0;">一键设置(一个部件对应一个对象)</el-button>
                        </div>
                    </div>
                </div>

                <div class="left-bottom">
                    <el-input v-model="keyword_object" size="mini" placeholder="搜索"></el-input>

                    <div class="left-bottom-msg">
                        <div>对象数量</div>
                        <div>[[objectList.filter(x=>x.belongPartName).length]]/[[objectList.length]]</div>
                    </div>

                    <div class="object-wrapper scrollable-content">
                        <div class="object" v-for="(item,index) of objectList" :key="'object_' + index"
                            @mouseenter="onMouseEnter(item.name)" @mouseleave="onMouseLeave(item.name)"
                            @click="onObjectClick(item)">
                            <i :class="item.belongPartName ? 'el-icon-check' : 'el-icon-more'"></i>
                            <div>[[item.name]]</div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="stage-center">
                <div ref="map" class="map"></div>

                <div class="version">
                    [[version]]
                </div>

                <div class="stage-tool-bottom">

                    <el-popover placement="top" width="260" trigger="hover">
                        <div class="control-pop">
                            <div class="control-item">
                                <i class="local-iconfont icon-danjishuangji1"></i>
                                <p>Ctrl+单击选部件</p>
                            </div>
                            <div class="control-item">
                                <i class="local-iconfont icon-danjishuangji1"></i>
                                <p>Shift+单击往部件添加或移除对象</p>
                            </div>
                            <div class="control-item">
                                <i class="local-iconfont icon-zuojianxuanzhuan1"></i>
                                <p>左键拖拽旋转</p>
                            </div>
                            <div class="control-item">
                                <i class="local-iconfont icon-youjiantuola1"></i>
                                <p>右键拖拽平移</p>
                            </div>
                            <div class="control-item">
                                <i class="local-iconfont icon-gunlunsuofang1"></i>
                                <p>滚轮滚动缩放</p>
                            </div>
                        </div>
                        <div class="stage-tool" slot="reference">
                            <i class="local-iconfont icon-kongzhianniu1"></i>
                            <p>控制</p>
                        </div>
                    </el-popover>

                    <div class="stage-tool">
                        <i class="local-iconfont  icon-a-haotubiao" @click="onShowTip"></i>
                        <p>指导</p>
                    </div>
                    <div class="stage-tool" @click="onResetView">
                        <i class="local-iconfont  icon-huifushijiao1"></i>
                        <p>恢复视角</p>
                    </div>
                    <div class="stage-tool" @click="onHideSet">
                        <i :class="`local-iconfont + ${hideSet?'icon-yincanganniu':'icon-kejiananniu'}`"></i>
                        <p>[[ hideSet?'显示已设':'隐藏已设']]</p>
                    </div>
                </div>

                <div class="part-map" v-if="selPart">
                    <div class="map-title">贴图位置</div>
                    <div class="map-param">
                        <div class="param-title">角度</div>
                        <div class="param-wrapper">
                            <el-slider v-model="selPart.map_param.angle" show-input :min="-180"
                                :max="180" :step="1"></el-slider>
                            <span>°</span>
                        </div>
                    </div>

                    <div class="map-param">
                        <div class="param-title">横向偏移</div>
                        <div class="param-wrapper">
                            <el-slider v-model="selPart.map_param.offset.x" show-input :min="-100"
                                :max="100" :step="1"></el-slider>
                            <span>%</span>
                        </div>
                    </div>

                    <div class="map-param">
                        <div class="detail-title">纵向偏移</div>
                        <div class="param-wrapper">
                            <el-slider v-model="selPart.map_param.offset.y" show-input :min="-100"
                                :max="100" :step="1"></el-slider>
                            <span>%</span>
                        </div>
                    </div>

                    <div class="map-clear">
                        <el-button type="primary" size="small" @click="onDefaultOffset">恢复默认</el-button>
                    </div>

                    <div class="map-title" style="margin-top:10px;">缩放</div>
                    <div class="map-param">
                        <div class="param-title">横向缩放</div>
                        <div class="param-wrapper">
                            <el-slider v-model="selPart.map_param.scale.x" show-input :min="0"
                                :max="1000" :step="1"></el-slider>
                            <span>%</span>
                        </div>
                    </div>

                    <div class="map-param">
                        <div class="param-title">纵向缩放</div>
                        <div class="param-wrapper">
                            <el-slider v-model="selPart.map_param.scale.y" show-input :min="0"
                                :max="1000" :step="1"></el-slider>
                            <span>%</span>
                        </div>
                    </div>

                    <div class="map-clear">
                        <el-button type="primary" size="small" @click="onDefaultScale">恢复默认</el-button>
                        <el-checkbox v-model="scaleRatio">等比例缩放</el-checkbox>
                    </div>
                </div>
            </div>


            <div class="stage-right">
                <div class="right-content scrollable-content">
                    <div class="right-title">属性</div>

                    <div class="right-name" v-if="selPart">
                        <div>名称</div>

                        <el-input v-model="selPart.name" size="mini" placeholder="请输入名称"
                            @change="hasSameName"></el-input>
                    </div>

                    <div class="right-color" v-if="selPart">
                        <div>可配置颜色</div>

                        <el-switch v-model="selPart.changeLock" :active-value="1" :inactive-value="0">
                        </el-switch>
                    </div>

                    <div class="right-visible" v-if="selPart">
                        <div>不可见部件</div>

                        <el-switch v-model="selPart.visible" :active-value="1" :inactive-value="0"
                            @change="onVisible(selPart)">
                        </el-switch>
                    </div>

                    <div class="right-mesh" v-if="selPart">
                        <div>已选对象</div>

                        <div class="mesh-rect">
                            <div class=" mesh-wrapper scrollable-content">
                                <div class="mesh" v-for="(item,index) of selPart.meshs_name"
                                    :key="'selPart_mesh_name_' + index" @mouseenter="onMouseEnter(item)"
                                    @mouseleave="onMouseLeave(item)">
                                    <div>[[item]]</div>
                                    <div @click="removeMeshName(item,index)">
                                        <i class="local-iconfont icon-shanchuanniu"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <el-collapse v-model="activeNames" accordion>
                        <el-collapse-item :title="`推荐配色【${colorPlan.data.length}】`" name="1">
                            <el-button size="small" style="width: 100%" @click="onColorPlanShow()"
                                :disabled="!hasColorPlan || !loadResult">编辑搭配</el-button>
                            <draggable v-model="colorPlan.data" class="plan-list-outside" v-bind="dragOptions"
                                item-key="uuid">
                                <div class="plan-outside" v-for="plan of colorPlan.data" :key="plan.uuid"
                                    @click="onColorPlanShow(plan)">
                                    <div class="plan-outside-title">
                                        <el-tooltip class="item" effect="dark" content="一键换装" placement="left"
                                            :open-delay="300">
                                            <i class="el-icon-magic-stick" @click.stop="onPlanDisplay(plan)"></i>
                                        </el-tooltip>

                                        <div class="name">[[plan.name]]</div>
                                    </div>

                                    <i class="el-icon-close close" @click.stop="deleteColorPlan(plan)"></i>
                                    <div class="part-list-outside">
                                        <div class="part" v-for="part of plan.partList"
                                            :key="part.id || part.uuid">
                                            <img :src="part.sel.thumb" />
                                        </div>
                                    </div>
                                </div>
                            </draggable>
                        </el-collapse-item>
                        <el-collapse-item title="材质贴图" name="2">
                            <div class="mat-default-wrapper" v-if="selPart">
                                <div>推荐材质</div>
                                <div class="mat-default">
                                    <div class="mat-color"
                                        :class="selPart.cur_color?.id == selPart.default_color?.id ? 'sel' : ''"
                                        v-if="selPart.default_color" @mouseenter="onMatEnter(selPart.default_color)"
                                        @mouseleave="onMatLeave" @click="onColorClick(selPart.default_color)">
                                        <img :src="selPart.default_color.thumb" />
                                        <div class="update-color"
                                            :class="enterColor?.id == selPart.default_color?.id ? 'show' : 'hide'"
                                            @click.stop="onAddMat(false,[selPart.default_color],selPart.select_color)">
                                            修改贴图
                                        </div>
                                    </div>
                                    <div class="mat-add" v-else
                                        @click="onAddMat(false,[selPart.default_color],selPart.select_color)"></div>
                                    <div class="mat-msg">
                                        <div>
                                            分组：[[selPart.default_color?.belongs_to_category?.name]]
                                        </div>
                                        <el-tooltip class="item" effect="dark"
                                            :content="selPart.default_color?.name" placement="top">
                                            <div>
                                                名称：[[selPart.default_color?.name]]
                                            </div>
                                        </el-tooltip>
                                    </div>
                                </div>
                            </div>

                            <div class="mat-more" v-if="selPart&&selPart.changeLock" v-if="selPart">
                                <div>可选材质</div>
                                <div class="more-rect">
                                    <div class="more-wrapper scrollable-content">
                                        <draggable v-model="selPart.select_color" v-bind="dragOptions"
                                            direction="horizontal" item-key="id" class="horizontal-list"
                                            :move="checkIfDraggable">

                                            <el-tooltip class="item" v-for="(item,index) of selPart.select_color"
                                                :key="'color_id_' + index" effect="dark" :content="item.name"
                                                placement="top" :open-delay="300">
                                                <div class="mat-color"
                                                    :class="selPart.cur_color?.id == item.id ? 'sel' : ''"
                                                    @click="onColorClick(item)" @mouseenter="onMatEnter(item)"
                                                    @mouseleave="onMatLeave">

                                                    <img :src="item.thumb" />

                                                    <div class="del-color"
                                                        :class="enterColor?.id == item.id ? 'show' : 'hide'"
                                                        @click.stop="onDelMat(item,index)">✕</div>


                                                    <div class="exchange-color"
                                                        :class="enterColor?.id == item.id ? 'show' : 'hide'"
                                                        @click.stop="onExchangeMat(item,index)">⇅</div>

                                                    <div class="color-name">[[item.name]]</div>
                                                </div>
                                            </el-tooltip>



                                            <template #footer>
                                                <div class="mat-add"
                                                    @click="onAddMat(true,selPart.select_color,[selPart.default_color])">
                                                </div>
                                            </template>

                                        </draggable>

                                    </div>
                                    <div class="more-clear">
                                        <el-button type="primary" size="mini"
                                            @click="onClearSelColor">色板重置</el-button>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-title-wrapper" v-if="selPart?.cur_color">
                                <div class="right-title">材质细节</div>
                                <el-button v-if="selPart.cur_color?.id === selPart.default_color?.id" type="primary"
                                    size="mini" @click="onSetDetailAll"
                                    :disabled="!canSetDetailAll">应用所有</el-button>
                            </div>


                            <div class="color-detail" v-if="selPart?.cur_color">
                                <div class="detail-title">粗糙度</div>
                                <div class="detail-wrapper">
                                    <el-slider v-model="selPart.cur_color.roughness" show-input :min="0"
                                        :max="1" :step="0.01"
                                        @input="onChangeMat"></el-slider>
                                </div>
                            </div>

                            <div class="color-detail" v-if="selPart?.cur_color">
                                <div class="detail-title">金属度</div>
                                <div class="detail-wrapper">
                                    <el-slider v-model="selPart.cur_color.metalness" show-input :min="0"
                                        :max="1" :step="0.01"
                                        @input="onChangeMat"></el-slider>
                                </div>
                            </div>

                            <div class="color-detail" v-if="selPart?.cur_color">
                                <div class="detail-title">不透明度</div>
                                <div class="detail-wrapper">
                                    <el-slider v-model="selPart.cur_color.opacity" show-input :min="0"
                                        :max="1" :step="0.01"
                                        @input="onChangeMat"></el-slider>
                                </div>
                            </div>
                        </el-collapse-item>
                    </el-collapse>

                </div>
                <div class="right-footer">
                    <el-button :disabled="!loadResult" type="primary" size="small" @click="onSure">保存</el-button>
                </div>
            </div>
        </div>

        {{-- 材质选择 --}}
        <el-dialog width="460px" :center="true" title="添加材质" :visible.sync="matVisible" append-to-body>
            <div class="mat-dialog">
                <div class="mat-top">
                    <div class="mat-search">
                        <el-input v-model="matSearchParam.search.name" placeholder="搜索" size="mini"
                            @input="getMaterial"></el-input>
                    </div>
                    <div class="mat-select">
                        <el-select v-model="matTypeLevel1Id" placeholder="请选择" size="mini" style="width:50%"
                            @change="getMaterial" clearable>
                            <el-option v-for="(item,index) in matTypeList" :key="'matType_' + index"
                                :label="item.name" :value="item.id">
                            </el-option>
                        </el-select>
                        <el-select v-model="matTypeLevel2Id" placeholder="请选择" size="mini" style="width:50%"
                            @change="getMaterial" clearable>
                            <el-option v-for="(item,index) in matTypeList2" :key="'matType2_' + index"
                                :label="item.name" :value="item.id">
                            </el-option>
                        </el-select>
                    </div>
                </div>
                <div class="mat-wrapper scrollable-content">
                    <el-tooltip class="item" v-for="(item,index) of matList" :key="'mat_' + index" effect="dark"
                        :content="item.name" placement="top" :open-delay="300">
                        <div class="mat"
                            :class="{
                                seled: matSelected.some(x => x.id == item.id),
                                sel: matSelList.some(x => x.id == item
                                    .id)
                            }"
                            @click="onClickMat(item)" @dblclick="onDbClickMat(item)">
                            <div class="png"><img :src="item.thumb"></div>
                            <div class="name">[[item.name]]</div>
                        </div>
                    </el-tooltip>
                </div>
            </div>

            <div slot="footer" class="dialog-footer">
                <el-button @click="onMatCancel">取 消</el-button>
                <el-button type="primary" @click="onMatSubmit">确 认</el-button>
            </div>
        </el-dialog>

        {{-- 模型检测 --}}
        <el-dialog width="800px" :center="true" title="模型检测报告" :visible.sync="modelCheck.visible"
            append-to-body>
            <template v-if="!modelCheck.isPass">
                <div class="mc-title" style="color:red;">模型检测不通过<span>(顶点数据不一致)</span></div>
                <div class="mc-item">
                    <el-button @click="onChangeModelByReport" size="mini" style="margin-top: 5px;"><i
                            class="el-icon-upload el-icon--left"></i>替换模型</el-button>
                </div>
            </template>
            <div class="mc-title">模型坐标<span>(坐标必须归零)</span></div>
            <div class="mc-item">x:<span
                    :class="computeError(modelCheck.position.x) == 0 ? 'success' : 'error'">[[modelCheck.position.x]]</span>
            </div>
            <div class="mc-item">y:<span
                    :class="computeError(modelCheck.position.y) == 0 ? 'success' : 'error'">[[modelCheck.position.y]]</span>
            </div>
            <div class="mc-item">z:<span
                    :class="computeError(modelCheck.position.z) == 0 ? 'success' : 'error'">[[modelCheck.position.z]]</span>
            </div>
            <div class="mc-title">模型离地面高度<span>(误差尽量控制在10mm内,最好为0mm)</span></div>
            <div class="mc-item">离地面距离:<span
                    :class="{
                        success: modelCheck.toGround == 0,
                        warn: Math.abs(modelCheck.toGround) > 0 && Math.abs(modelCheck.toGround) <= 10,
                        error: Math.abs(modelCheck.toGround) > 10
                    }">[[modelCheck.toGround]]</span>mm
            </div>
            <div class="mc-item">
                <span v-if="modelCheck.toGround == 0" class="success">模型已贴地</span>
                <span v-else-if="modelCheck.toGround > 0"
                    :class="Math.abs(modelCheck.toGround) > 0 && Math.abs(modelCheck.toGround) <= 10 ? 'warn' : 'error'">模型已悬空</span>
                <span v-else :class="Math.abs(modelCheck.toGround) <= 10 ? 'warn' : 'error'">模型已陷入地面</span>
            </div>
            <div class="mc-title">模型尺寸<span>(误差最好在10mm内)</span></div>
            <div class="mc-item">
                长:<span
                    :class="{
                        success: computeError(modelCheck.size.x * 1000, d3ModelData.length) < 1,
                        warn: computeError(modelCheck.size.x * 1000, d3ModelData.length) >= 1 &&
                            computeError(modelCheck
                                .size.x * 1000, d3ModelData.length) <= 10,
                        error: computeError(modelCheck.size.x * 1000, d3ModelData.length) > 10
                    }">[[(modelCheck.size.x*1000).toFixed(0)]]</span>mm
                <span>(填入值:[[parseFloat(d3ModelData.length).toFixed(0)]] mm)</span>
            </div>
            <div class="mc-item">宽:<span
                    :class="{
                        success: computeError(modelCheck.size.z * 1000, d3ModelData.width) < 1,
                        warn: computeError(modelCheck.size.z * 1000, d3ModelData.width) >= 1 &&
                            computeError(modelCheck.size.z * 1000, d3ModelData.width) <= 10,
                        error: computeError(modelCheck.size.z * 1000, d3ModelData.width) > 10
                    }">[[(modelCheck.size.z*1000).toFixed(0)]]</span>mm
                <span>(填入值:[[parseFloat(d3ModelData.width).toFixed(0)]] mm)</span>
            </div>
            <div class="mc-item">高:<span
                    :class="{
                        success: computeError(modelCheck.size.y * 1000, d3ModelData.height) < 1,
                        warn: computeError(modelCheck.size.y * 1000, d3ModelData.height) >= 1 &&
                            computeError(modelCheck.size.y * 1000, d3ModelData.height) <= 10,
                        error: computeError(modelCheck.size.y * 1000, d3ModelData.height) > 10
                    }">[[(modelCheck.size.y*1000).toFixed(0)]]</span>mm
                <span>(填入值:[[parseFloat(d3ModelData.height).toFixed(0)]] mm)</span>
            </div>
            <div class="mc-item">
                包围盒:<el-switch v-model="modelCheck.sizeBox" active-color="#13ce66" @change="onChangeSizeBox">
                </el-switch>
            </div>

            <div class="mc-title">模型顶点数据<span>(红色表示缺少该项数据)</span></div>
            <div class="mc-item" v-for="(item,index) of modelCheck.attribute" :key="'attribute_' + index">
                [[item.name]]:
                <span :class="item.attributes.includes('position') ? 'success' : 'error'">坐标(position)</span>
                <span :class="item.attributes.includes('normal') ? 'success' : 'error'">法线(normal)</span>
                <span :class="item.attributes.includes('uv') ? 'success' : 'error'">纹理(uv)</span>
                <span v-for="attr of item.attributes.filter(x=>x!='position'&&x!='normal'&&x!='uv')"
                    class="info">[[attr]]</span>
            </div>

            <div class="mc-title">模型缩放<span>(最好接近正负1)</span></div>
            <div class="mc-item" v-for="(item,index) of modelCheck.scale" :key="'scale_' + index">
                [[item.name]]:
                x:<span :class="computeScale(item.scale.x) ? 'success' : 'warn'">[[item.scale.x]]</span>
                y:<span :class="computeScale(item.scale.y) ? 'success' : 'warn'">[[item.scale.y]]</span>
                z:<span :class="computeScale(item.scale.z) ? 'success' : 'warn'">[[item.scale.z]]</span>
            </div>
        </el-dialog>

        {{-- 模型减面人工上传 --}}
        <el-dialog width="90vw" :center="true" top="5vh" title="模型减面"
            :visible.sync="modelSlim.visible" append-to-body :close-on-click-modal="false"
            :close-on-press-escape="false" :show-close="false">
            <div class="slim-container" element-loading-spinner="el-icon-loading" v-loading="modelSlim.loading"
                :element-loading-text="modelSlim.loadingText" element-loading-background="rgba(255, 255, 255, 0.9)">
                <div ref="slimMap" class="slim-map"></div>
                <div class="slim-right">
                    <div class="part part-title">
                        <div>
                            对象
                        </div>
                        <div>
                            源模型面数：[[totalOriFaces]]
                        </div>
                        <div>
                            当前模型面数：[[totalSlimFaces]]
                        </div>
                    </div>

                    <div class="part-wrapper">
                        <div class="part" :class="{ sel: modelSlim.selObject?.name == item.name }"
                            v-for="(item,index) of modelSlim.objects" :key="index"
                            @click="onSelSlimObject(item)">
                            <div>
                                [[item.name]]
                            </div>
                            <div class="all-faces">
                                [[item.oriFaces]]
                            </div>
                            <div class="retain-faces">
                                [[item.curFaces]]
                            </div>
                        </div>
                    </div>

                    <div class="tip-wrapper">
                        <el-tooltip class="item" effect="dark" content="修改减面模型的面数将重新对模型减面，模型实际的面数于输入的面试会有误差浮动"
                            placement="top">
                            <i class="el-icon-question el-icon--left"></i>
                        </el-tooltip>
                        <el-tooltip class="item" effect="dark" content="修改的面数重置为当前减面模型的面数" placement="top">
                            <el-button @click="onSlimResetFace" size="mini">重 置</el-button>
                        </el-tooltip>

                    </div>

                    <div class="func-wrapper">

                        <div class="func">
                            <div class="title">显示面格</div>
                            <el-switch v-model="modelSlim.wireframe" @change="onWireFrame">
                            </el-switch>
                        </div>

                        {{-- <div class="func">
                            <el-upload action="" :on-change="onSlimChange" :auto-upload="false"
                                :show-file-list="false">
                                <el-button class="onekey" style="width: 100%" type="primary"
                                    ref="slimBtnRef">替换模型</el-button>
                            </el-upload>

                            <el-button type="warning" @click="onSlimDel">删除模型</el-button>
                        </div> --}}

                        <div class="face">
                            <div class="title">减面模型面数</div>
                            <el-slider class="slider" v-model="modelSlim.total_face" :min="0"
                                :max="totalOriFaces"></el-slider>
                            <el-input-number class="input" v-model="modelSlim.total_face" :controls="false"
                                :min="0" :max="totalOriFaces"></el-input-number>
                        </div>

                    </div>

                    <div slot="footer" class="dialog-footer">
                        <el-button @click="onSlimCancel">关 闭</el-button>
                        <el-button type="primary" @click="onSlimSubmit">保 存</el-button>
                    </div>
                </div>
            </div>
        </el-dialog>

        {{-- 配色方案 --}}
        <el-dialog width="740" :center="true" top="5vh" title="推荐配色"
            :visible.sync="colorPlan.visible" append-to-body :close-on-click-modal="false"
            :close-on-press-escape="false" :show-close="false">
            <div class="plan-list">
                <draggable v-model="colorPlan.data" class="plan-list-scroll" v-bind="dragOptions" item-key="uuid"
                    ref="planScrollRef">
                    <div class="plan" :class="{ sel: colorPlan.sel?.uuid == plan.uuid }"
                        v-for="plan of colorPlan.data" :key="plan.uuid" :ref="'planRef_' + plan.uuid">
                        <el-input class="input" v-model="plan.name" placeholder="请输入配色名称"
                            @focus="selectColorPlan(plan)"></el-input>
                        <i class="el-icon-circle-close close" @click="deleteColorPlan(plan)"></i>
                    </div>
                </draggable>
                <el-button class="btn-add" size="small" @click="createColorPlan">添加颜色</el-button>
            </div>
            <div class="part-list">
                <div class="part" v-for="part of colorPlan.sel?.partList" :key="part.uuid">
                    <div class="name">[[part.name]]</div>
                    <div class="color-list">
                        <div class="color" :class="{ sel: part.sel?.id == color.id }"
                            v-for="color of part.colorList" :key="color.id">
                            <div class="thumb" @click="selectPlanPartColor(part,color)"><img
                                    :src="color.thumb" /></div>
                            <div class="text">[[color.name]]</div>
                        </div>
                    </div>
                </div>
            </div>
            <div slot="footer" class="dialog-footer">
                <el-button @click="onColorPlanCancel">取 消</el-button>
                <el-button @click="onColorPlanSure">确 认</el-button>
            </div>
        </el-dialog>
    </el-dialog>
</template>

{{-- Aman_log:模型上传 --}}

<script type="module">
    // const ex2_url = "{{ static_url('resource/ex2/brown_photostudio_04_2k.exr') }}"

    import Stage from "{{ static_url('../resources/views/public/admin/modules/three/stage.js') }}";

    Vue.directive("focus", {
        inserted(el, binding, vnode) {
            el.querySelector('input').focus();
        }
    })

    Vue.component('threeStage', {
        delimiters: ['[[', ']]'],
        template: "#three-stage",
        props: {
            visible: {
                type: Boolean,
                default: false,
            },
            d3ModelData: {
                type: Object,
                default: {},
            },
            baseParts: {
                type: Array,
                default: () => {
                    return []
                }
            }
        },
        data() {
            return {
                loading: false,
                loadResult: false,

                /** left-top */
                selPart: null,

                /** left-bottom */
                keyword_object: "",
                allObjectList: [],
                objectList: [],


                /** three */
                stage: null,
                hoverNames: [],

                /** part */
                scaleRatio: false,
                recordScale: {},

                /** material */
                matVisible: false,
                matSelMulti: false,
                matSelList: [],
                matSelected: [],
                matSearchParam: {
                    stype: 1,
                    search: {
                        cate_id: null,
                        name: ""
                    }
                },
                matTypeList: [],
                matTypeLevel1Id: null,
                matTypeList2: [],
                matTypeLevel2Id: null,
                matList: [],

                enterColor: null,

                hideSet: false,

                oriData: {},

                /** gui */

                clarity: 1,
                marks: {
                    0.5: '模糊',
                    0.75: '较模糊',
                    1: '标清',
                    1.5: '高清',
                    2: '超清',
                },

                renderTarget: false,

                fov: 65,

                /** drag */
                dragOptions: {
                    group: {
                        name: "shared",
                        pull: "clone",
                        put: true
                    },
                    animation: 200,
                    dragClass: "dragging", // 拖拽时的样式类
                    ghostClass: "ghost", // 占位符的样式类
                    chosenClass: "chosen", // 选中项的样式类
                    disabled: false,
                },

                /** 模型检测 */
                modelCheck: {
                    visible: false,
                    position: {},
                    size: {},
                    sizeBox: false,
                    scale: [],
                    attribute: [],
                    isPass: false,
                },

                /** 模型减面 */
                modelSlim: {
                    visible: false,
                    loading: false,
                    loadingText: "模型加载中...",
                    wireframe: false,
                    objects: [], // name,oriFaces,curFaces
                    selObject: null,
                    url: null,
                    urlModel: null,
                    total_face: 0,
                },

                activeNames: "1",

                colorPlan: {
                    visible: false,
                    data: [],
                    sel: null,
                    recordData: [],
                },

                version: "-",
            }
        },
        computed: {
            canSetDetailAll() {
                if (!this.selPart) return false;

                if (!this.selPart.select_color) return false;

                const {
                    roughness,
                    metalness,
                    opacity
                } = this.selPart.default_color;

                return this.selPart.select_color.some(x => x.roughness != roughness || x.metalness !=
                    metalness || x.opacity != opacity);
            },

            totalOriFaces() {
                return this.modelSlim.objects.reduce((acc, x) => {
                    acc += x.oriFaces || 0;
                    return acc;
                }, 0) || 0
            },

            totalSlimFaces() {
                return this.modelSlim.objects.reduce((acc, x) => {
                    acc += x.curFaces || 0
                    return acc;
                }, 0) || 0
            },

            hasColorPlan() {
                let res = true;

                const {
                    productType,
                    modelType
                } = this.d3ModelData;

                // 常规商品
                if (productType == 1 || productType == 5) {
                    res = true;
                }
                // 双模型
                else if (productType == 2) {
                    res = modelType == 0;
                }
                // 四模型
                else if (productType == 3) {
                    res = modelType == 0;
                }
                // 屏风
                else if (productType == 4) {
                    res = modelType == 0 || modelType == 1 || modelType == 2;
                }

                return res;
            }
        },
        watch: {
            visible: {
                async handler(newVal) {
                    if (newVal) {
                        await this.getMaterialType();
                        await this.getMaterial();

                        this.$nextTick(async () => {

                            try {

                                this.loading = true;

                                if (!this.stage) {
                                    this.stage = new Stage(this.$refs.map);
                                    this.stage.setClickCallback(this.onStageClick);
                                    this.stage.setControlsChangeCallback(this.onControlsChange);
                                    this.stage.setControlsEndCallback(this.onControlsEnd);
                                    // await this.stage.initLights(ex2_url);
                                    this.stage.initLights();
                                    this.version = this.stage.getVersion() + this.getVersion();
                                }

                                this.stage.startRender();

                                this.loadResult = this.d3ModelData.d3ModelUrl_ori_file ?
                                    await this.stage.loadModelByFile(this.d3ModelData
                                        .d3ModelUrl_ori_file) :
                                    await this.stage.loadModel(this.d3ModelData.d3ModelUrl_ori);

                                // await this.testNet(30 * 1000)

                                if (!this.loadResult) {
                                    this.d3ModelData.model_param.length = 0;
                                    this.objectList.length = 0;
                                    this.$message.error("加载模型失败");
                                    return;
                                }


                                // 检测报告
                                if (this.d3ModelData.d3ModelUrl_ori_file && !this.d3ModelData
                                    .d3ModelUrl_buffer && !this.d3ModelData.d3ModelUrl_ori) {
                                    this.modelCheck.isPass = this.checkReport(true);
                                } else {
                                    this.modelCheck.isPass = this.checkReport(false);
                                }

                                if (!this.modelCheck.isPass) {
                                    this.clearUnPass();
                                    return;
                                }


                                // 模型减面(重置)
                                this.modelSlim.visible = false;
                                this.modelSlim.loading = false;
                                this.modelSlim.loadingText = "模型加载中...";
                                this.modelSlim.wireframe = false;
                                this.modelSlim.selObject = null;
                                this.modelSlim.url = this.d3ModelData.thumb3dModelUrl || null;
                                this.modelSlim.urlModel = null;
                                this.modelSlim.total_face = this.d3ModelData.total_face || 0;

                                this.initParts();

                                this.initObjects();

                                this.initModelStyle();

                                // 配色方案
                                this.initColorPlan(this.d3ModelData.colorPlan);
                                // 防止有时会出现加载后需要改变窗口大小再显示
                                setTimeout(() => {
                                    this.stage.onWindowResize();
                                }, 1000);

                            } catch (error) {
                                console.error(error);
                            } finally {
                                (() => {
                                    this.loading = false;
                                })();
                            }
                        });
                    } else {
                        if (this.stage) {
                            this.stage.delModel();

                            this.resetSizeBox();

                            setTimeout(() => {
                                this.stage.stopRender();
                                this.reset();
                            }, 10);
                        }
                    }
                },
                immediate: true,
            },

            keyword_object(newVal) {
                this.objectList = this.allObjectList.filter(x => x.name.includes(newVal));
            },

            hoverNames(newVal) {
                this.stage.setHoverNames(newVal);
            },

            selPart(newVal, oldVal) {
                this.activeNames = newVal ? "2" : "1";
                if (newVal?.uuid != oldVal?.uuid) {
                    this.selPartNameUnWatchOnce = true;
                }
            },

            'selPart.name': {
                async handler(newVal, oldVal) {
                    if (this.selPartNameUnWatchOnce) {
                        // 重置标志
                        this.selPartNameUnWatchOnce = false;
                        return;
                    }

                    if (newVal == undefined || oldVal == undefined) return;

                    const res = await this.clearPlan();

                    if (!res) {
                        this.selPart.name = oldVal;
                        this.selPartNameUnWatchOnce = true;
                    }
                },
            },

            'selPart.map_param': {
                async handler(newVal, oldVal) {
                    this.stage.setMapDetail(this.selPart);
                },
                deep: true
            },

            'selPart.meshs_name': {
                async handler(newVal) {
                    this.stage.setSelectNames(newVal ? newVal : []);
                },
                deep: true
            },

            'selPart.map_param.scale.x': function(newVal, oldVal) {
                if (this.scaleRatio && this.selPart) {
                    this.selPart.map_param.scale.y = newVal;
                }
            },

            'selPart.map_param.scale.y': function(newVal, oldVal) {
                if (this.scaleRatio && this.selPart) {
                    this.selPart.map_param.scale.x = newVal;
                }
            },

            matTypeLevel1Id(newVal) {
                this.matTypeList2 = newVal ? this.matTypeList.find(x => x.id == newVal)?.has_many_children : [];
                this.matTypeLevel2Id = null;
            },

            clarity(newVal) {
                this.stage.setClarity(newVal);
            },

            renderTarget(newVal) {
                this.stage.setRenderTarget(newVal);
            },

            fov(newVal) {
                this.stage.setCameraFov(newVal);
            },

            'modelSlim.visible': function(newVal) {

                if (newVal) {
                    this.resetSizeBox();

                    const objects = this.stage.getObjects();

                    this.modelSlim.objects = objects.map(mesh => {
                        const name = mesh.name;
                        const oriFaces = this.stage.getObjectFacesByName(name);
                        const curFaces = 0;
                        return {
                            name,
                            oriFaces,
                            curFaces
                        }
                    })

                    this.$nextTick(async () => {

                        // if (this.modelSlim.oriModelData) {
                        //     if (!this.modelSlim.oriModel) this.modelSlim.oriModel = await this
                        //         .stage
                        //         .loadModelSlim(this.modelSlim.oriModelData)

                        //     this.onSlimRecord();
                        //     this.stage.openSlim(this.$refs.slimMap, this.modelSlim.oriModel);

                        //     this.modelSlim.objects.map(x => {
                        //         x.curFaces = this.stage.getObjectFacesByName(x.name);
                        //     })
                        // } else {
                        //     this.onSlimRecord();
                        //     this.stage.openSlim(this.$refs.slimMap);
                        //     this.$refs.slimBtnRef.$el.click();
                        // }

                        if (this.modelSlim.url) {
                            if (!this.modelSlim.urlModel) this.modelSlim.urlModel = await this
                                .stage
                                .loadModelSlim(this.modelSlim.url)

                            this.onSlimRecord();
                            this.stage.openSlim(this.$refs.slimMap, this.modelSlim.urlModel);

                            this.modelSlim.objects.map(x => {
                                x.curFaces = this.stage.getObjectFacesByName(x.name);
                            })
                        } else {
                            this.onSlimRecord();
                            this.stage.openSlim(this.$refs.slimMap);
                        }
                    })

                } else {
                    this.stage.closeSlim();
                }
            },
        },

        methods: {
            //#region dislog 方法

            handleClose(done) {
                if (this.deepEqual(this.oriData, this.d3ModelData)) {
                    this.onClose();
                } else {
                    this.$confirm('当前改动未保存! 是否坚持退出?', '温馨提示', {
                        confirmButtonText: '确定',
                        cancelButtonText: '取消',
                        type: 'warning'
                    }).then(() => {
                        this.onClose();
                        // done();
                    }).catch(() => {
                        this.$message({
                            type: 'info',
                            message: '已取消'
                        });
                    });
                }

            },

            onCancel() {
                this.onClose();
            },

            async onSure() {

                /* 测试导出模型 */
                // this.stage.getBuildModel(this.d3ModelData);
                // return;

                try {
                    /** 部件名称不能重复 */
                    if (!this.hasSameName()) return;

                    /** 不能存在没放入部件的对象 */
                    const unBelong = this.allObjectList.filter(x => x.belongPartName == "").map(x => x
                        .name);
                    if (unBelong.length) {
                        this.$message.error(`对象【${unBelong.join(',')}】未被放入部件,保存失败!`);
                        return;
                    }

                    /** 部件一定要有默认颜色 */
                    const unDefaultColor = this.d3ModelData.model_param.filter(part => !part
                        .default_color &&
                        part.visible == 0).map(x => x.name);
                    if (unDefaultColor.length) {
                        this.$message.error(`部件【${unDefaultColor.join(',')}】未选择推荐材质!`);
                        return;
                    }

                    /** 提示减面 */
                    // const slimPromise = new Promise((resolve, reject) => {
                    //     if (!this.modelSlim.isDeal) {
                    //         this.$confirm('未上传减面模型，若在云设计里使用，将可能影响性能', '温馨提示', {
                    //             confirmButtonText: '去上传',
                    //             cancelButtonText: '忽略',
                    //             type: 'warning'
                    //         }).then(() => {
                    //             this.onModelSlim();
                    //             resolve(false);
                    //         }).catch(() => {
                    //             resolve(true);
                    //         });
                    //     } else {
                    //         resolve(true);
                    //     }
                    // })
                    // const slimPass = await slimPromise;
                    // if (!slimPass) return;


                    this.d3ModelData.d3ModelUrl_buffer = await this.stage.getBuildData(this.d3ModelData);

                    /** 部件添加下标 */
                    this.d3ModelData.model_param.map((part, index) => {
                        part.index = index;
                    })

                    /** 配色方案*/
                    const {
                        data,
                        unPassPlan
                    } = this.getSaveColorPlan();
                    if (unPassPlan.length) {
                        this.$message.error(`配色方案【${unPassPlan.join(",")}】未选中颜色，保存失败`);
                        return;
                    }
                    this.d3ModelData.colorPlan = data;

                    /** 减面模型的面数 */
                    // this.d3ModelData.total_face = this.totalSlimFaces || this.d3ModelData.total_face || 0;
                    if (this.d3ModelData.thumb3dModelUrl) this.d3ModelData.total_face = this.modelSlim
                        .total_face;

                    console.log(this.d3ModelData);

                    this.onClose();

                    this.$emit('save');

                    this.$message.success("保存成功");

                } catch (err) {
                    this.$message.error("保存失败");
                    console.error(err);
                }

            },

            onClose() {
                this.$emit('update:visible', false);
            },

            //#endregion

            reset() {
                this.selPart = null;

                this.keyword_object = "";

                this.matSearchParam = {
                    stype: 1,
                    search: {
                        cate_id: null,
                        name: ""
                    }
                };

                this.hideSet = false;
            },

            checkReport(visible) {
                this.modelCheck.visible = visible;
                this.modelCheck.position = this.stage.getModelPosition();
                this.modelCheck.toGround = this.stage.getModelToGround();
                this.modelCheck.size = this.stage.getModelSize();
                this.modelCheck.scale = this.stage.getModelScale();
                this.modelCheck.attribute = this.stage.getModelAttribute();

                let isPass = true;

                const attrFirst = this.modelCheck.attribute[0].attributes.join(",");

                if (attrFirst) {
                    for (let i = 1; i < this.modelCheck.attribute.length; i++) {
                        const attr = this.modelCheck.attribute[i].attributes.join(",");
                        if (attrFirst != attr) {
                            // setTimeout(() => {
                            //     this.$message({
                            //         message: '模型顶点数据不一致，可能导致保存失败，建议重新调整模型再执行后续操作',
                            //         type: 'warning',
                            //         zIndex: 3000
                            //     });
                            // }, 0);
                            isPass = false;
                            break;
                        }
                    }
                }

                return isPass;
            },

            clearUnPass() {
                this.stage.delModel();
            },

            computeError(val1, val2 = 0) {
                return Math.abs(parseFloat(val1) - parseFloat(val2));
            },

            computeScale(val) {
                const scale = Math.abs(val);
                return scale > 0.9 && scale < 1.1;
            },

            resetSizeBox() {
                this.modelCheck.sizeBox = false;
                this.onChangeSizeBox();
            },

            onChangeSizeBox() {
                if (this.stage) {
                    this.modelCheck.sizeBox ? this.stage.showBoxHelper() : this.stage.disposeBoxHelper();
                }
            },

            //#region init method

            initParts() {
                for (let part of this.d3ModelData.model_param) {

                    part.map_param = part.map_param ? {
                        angle: parseInt(part.map_param.angle),
                        offset: {
                            x: parseInt(part.map_param.offset.x),
                            y: parseInt(part.map_param.offset.y),
                        },
                        scale: {
                            x: parseInt(part.map_param.scale.x),
                            y: parseInt(part.map_param.scale.y),
                        }
                    } : {
                        angle: 0,
                        offset: {
                            x: 0,
                            y: 0
                        },
                        scale: {
                            x: 100,
                            y: 100,
                        }
                    }

                    part.meshs_name = part.meshs_name || [];

                    part.uuid = part.id || part.uuid || this.getUUID();

                    part.map_param_ori = part.map_param_ori || this.deepClone(part.map_param);

                    /** 细节参数转浮点 */
                    [part.default_color, ...part.select_color].map(color => {
                        if (color && color != "null" && color != "undefined") {
                            color.roughness = parseFloat(color.roughness);
                            color.metalness = parseFloat(color.metalness);
                            color.opacity = parseFloat(color.opacity);
                        }
                    })

                    part.cur_color = part.default_color;

                }

                /** 备份对比 */
                this.oriData = this.deepClone(this.d3ModelData);

                // if (this.d3ModelData.model_param.length) this.selPart = this.d3ModelData.model_param[0];

            },

            initObjects() {
                const meshList = this.stage.getObjects();

                this.allObjectList.length = 0;

                meshList.map(mesh => {
                    const obj = {
                        name: mesh.name,
                        belongPartName: "",
                    }

                    for (let part of this.d3ModelData.model_param) {
                        if (part.meshs_name.includes(obj.name)) {
                            obj.belongPartName = part.name;
                            break;
                        }
                    }

                    this.allObjectList.push(obj);
                })

                this.objectList = [...this.allObjectList];
            },

            initModelStyle() {
                this.d3ModelData.model_param.map(async (part) => {
                    if (part.visible) this.stage.hideMeshs(part.meshs_name);
                    await this.updateMat(part, part.meshs_name, part.cur_color);
                })
            },

            //#endregion




            deepClone(obj) {
                if (obj === null || typeof obj !== 'object') {
                    return obj; // 处理基本类型
                }
                if (Array.isArray(obj)) {
                    const arrCopy = [];
                    for (let item of obj) {
                        arrCopy.push(this.deepClone(item)); // 递归拷贝数组元素
                    }
                    return arrCopy;
                }
                const objCopy = {};
                for (let key in obj) {
                    if (obj.hasOwnProperty(key)) {
                        objCopy[key] = this.deepClone(obj[key]); // 递归拷贝对象属性
                    }
                }
                return objCopy;
            },

            deepEqual(obj1, obj2) {
                if (obj1 === obj2) return true; // 引用相同
                if (obj1 == null || obj2 == null || typeof obj1 !== 'object' || typeof obj2 !== 'object') {
                    return false; // 处理基本类型和 null
                }
                const keys1 = Object.keys(obj1);
                const keys2 = Object.keys(obj2);
                if (keys1.length !== keys2.length) return false; // 属性数量不同
                for (let key of keys1) {
                    if (!keys2.includes(key) || !this.deepEqual(obj1[key], obj2[key])) {
                        return false; // 属性不同或值不同
                    }
                }
                return true;
            },

            //#region 头部方法
            //--------

            onChangeModel() {
                this.$refs.fileInput.click();
            },

            onChangeModelByReport() {
                this.modelCheck.visible = false;
                this.$refs.fileInput.click();
            },

            async glbInputChange(event) {
                try {
                    this.loading = true;

                    const file = event.target.files[0];
                    if (file) {
                        this.stage.delModel();
                        this.d3ModelData.d3ModelUrl_ori_file = file;

                        this.loadResult = await this.stage.loadModelByFile(file);
                        if (!this.loadResult) {
                            this.d3ModelData.model_param.length = 0;
                            this.objectList.length = 0;
                            this.$message.error("加载模型失败");
                            return;
                        }

                        this.modelCheck.isPass = this.checkReport(true);
                        if (!this.modelCheck.isPass) {
                            this.clearUnPass();
                            return;
                        }

                        this.initObjects();
                        this.d3ModelData.model_param.map(part => part.meshs_name.length = 0);
                        this.allObjectList.map(x => x.belongPartName = "");
                        this.$refs.fileInput.value = "";
                        this.resetSizeBox();

                        this.modelSlim.isDeal = false;
                    }

                } finally {
                    this.loading = false;
                }

            },

            onModelSlim() {
                this.modelSlim.visible = true;
            },

            //--------
            //#endregion

            //#region 左边部件方法
            //--------

            clearPart() {
                this.$confirm('确认清空部件吗?', '提示', {
                    confirmButtonText: '确定',
                    cancelButtonText: '取消',
                    type: 'warning'
                }).then(() => {
                    this.selPart = null;

                    this.d3ModelData.model_param.map(x => {
                        this.stage.clearPartColor(x.meshs_name);
                        this.stage.clearPartColorDetail(x.meshs_name);
                        this.stage.partNeedUpdate(x.meshs_name);
                        this.stage.showMeshs(x.meshs_name);
                    })

                    this.d3ModelData.model_param.length = 0;

                    this.allObjectList.map(x => {
                        x.belongPartName = "";
                    });

                    this.$forceUpdate();

                }).catch(() => {
                    this.$message({
                        type: 'info',
                        message: '已取消'
                    });
                });
            },

            async newPart(selIt = false) {
                const res = await this.clearPlan();

                if (!res) return;

                const part = {
                    name: this.getDefaultPartName(),
                    option_id: this.d3ModelData.option_id,
                    changeLock: 1, // 可以修改颜色:1,不可修改颜色:0
                    default_color: null,
                    select_color: [],
                    visible: 0, // 可见:0,不可见:1

                    /** 新加数据 */
                    map_param: {
                        angle: 0,
                        offset: {
                            x: 0,
                            y: 0
                        },
                        scale: {
                            x: 100,
                            y: 100,
                        }
                    },
                    meshs_name: [], // 存储包含对象名称

                    /** 不存储 */
                    uuid: this.getUUID(),
                    map_param_ori: {
                        angle: 0,
                        offset: {
                            x: 0,
                            y: 0
                        },
                        scale: {
                            x: 100,
                            y: 100,
                        }
                    }, // 用于重置
                    cur_color: null, // 用于存储当前颜色的选择
                };

                this.d3ModelData.model_param.push(part);

                if (selIt) {
                    this.selPart = part;
                } else {
                    if (!this.selPart) {
                        this.selPart = part;
                    }
                }

                return part;
            },

            onImportPart() {

                this.d3ModelData.model_param = this.deepClone(this.baseParts);

                this.d3ModelData.model_param.map(part => {
                    part.id = undefined;
                    part.meshs_name.length = 0;
                });

                this.allObjectList.map(obj => obj.belongPartName = "");

                this.initParts();
            },

            async onPartByObject() {
                for (let obj of this.allObjectList) {
                    const part = await this.newPart();
                    part.name = obj.name;
                    part.meshs_name.push(obj.name);
                    obj.belongPartName = part.name;
                }
            },



            onSelPart(part) {
                this.selPart = part;
            },

            hasSameName() {
                const hasDuplicates = new Set(this.d3ModelData.model_param.map(x => x.name)).size !== this
                    .d3ModelData.model_param.length;

                if (hasDuplicates) {
                    this.$message.error("部件名称不能一样!");
                    return false;
                } else {
                    return true;
                }
            },

            getMaterialCount(item) {
                return item.changeLock ? ([item.default_color, ...item.select_color].filter(x => x).length >
                        99 ?
                        '99+' : [item.default_color, ...item.select_color].filter(x => x).length) :
                    item.default_color ? 1 : 0
            },

            onClonePart(part) {
                const clonePart = JSON.parse(JSON.stringify(part));

                clonePart.meshs_name = [];
                clonePart.name += "(克隆)";
                clonePart.uuid = this.getUUID();
                delete clonePart.id;

                this.d3ModelData.model_param.push(clonePart);
                this.selPart = clonePart;

                this.$message.success("克隆成功");
            },

            async onchangeLock(part) {
                const res = await this.clearPlanByPart(part);

                if (!res) return;

                part.changeLock = part.changeLock == 1 ? 0 : 1;
            },

            async onDisVisible(part) {
                const res = await this.clearPlanByPart(part);

                if (!res) return;

                part.visible = part.visible == 0 ? 1 : 0;
                this.onVisible(part);
            },

            onVisible(part) {
                if (part.visible) {
                    this.stage.hideMeshs(part.meshs_name);
                } else {
                    if (!this.hideSet) this.stage.showMeshs(part.meshs_name);
                }
            },

            async onDeletePart(item, index) {
                const res = await this.clearPlanByPart(item);

                if (!res) return;

                this.stage.clearPartColor(item.meshs_name);
                this.stage.clearPartColorDetail(item.meshs_name);
                this.stage.partNeedUpdate(item.meshs_name);
                this.stage.showMeshs(item.meshs_name);


                this.allObjectList.map(x => {
                    if (item.meshs_name.includes(x.name)) {
                        x.belongPartName = "";
                    }
                })

                const isLast = index == this.d3ModelData.model_param.length - 1;

                this.d3ModelData.model_param.splice(index, 1);

                if (this.selPart.uuid == item.uuid) {
                    this.selPart = isLast ? this.d3ModelData.model_param[index - 1] : this.d3ModelData
                        .model_param[index];
                }

                if (!this.selPart) this.stage.setSelectNames([]);
            },

            getDefaultPartName() {
                const oriPartName = "默认部件";

                let isExist = this.d3ModelData.model_param.some((x) => x.name == oriPartName);

                if (!isExist) return oriPartName;

                let cloneIndex = 1;

                let clonePartName = oriPartName;

                while (isExist) {
                    clonePartName = `${oriPartName}(${cloneIndex++})`;
                    isExist = this.d3ModelData.model_param.some((x) => x.name == clonePartName);
                }

                return clonePartName;
            },

            getUUID() {
                let isOnly = false,
                    uuid;

                while (!isOnly) {
                    uuid = Math.random().toString(36).substr(2, 32);
                    isOnly = this.d3ModelData.model_param.every((x) => x.uuid != uuid);
                }

                return uuid;
            },

            //--------
            //#endregion


            //#region 左边对象方法
            //--------



            async onObjectClick(item) {
                if (!this.selPart) {
                    this.$message({
                        message: "请先选中一个部件",
                        type: 'info'
                    });
                    return;
                }

                if (!item.belongPartName) {
                    item.belongPartName = this.selPart.name;
                    this.selPart.meshs_name.push(item.name);

                    if (this.selPart.cur_color) {
                        await this.updateMat(this.selPart, [item.name], this.selPart.cur_color);
                    }

                    if (this.hideSet || this.selPart.visible) {
                        this.stage.hideMeshs([item.name])
                    }
                } else {
                    const index = this.selPart.meshs_name.findIndex(name => name == item.name);

                    if (index >= 0) {
                        item.belongPartName = "";
                        this.selPart.meshs_name.splice(index, 1);
                        this.stage.clearPartColor([item.name]);
                        this.stage.clearPartColorDetail([item.name]);
                        this.stage.partNeedUpdate([item.name]);
                        this.stage.showMeshs([item.name])
                    } else {
                        this.$message({
                            message: `该对象已属于部件【${item.belongPartName}】,要更改需先在所属部件移除该对象`,
                            type: 'info'
                        });
                    }
                }
            },

            /** 右边属性公用 */
            onMouseEnter(name) {
                this.hoverNames.push(name);
            },

            /** 右边属性公用 */
            onMouseLeave(name) {
                const index = this.hoverNames.findIndex(x => x == name);
                if (index >= 0) this.hoverNames.splice(index, 1);
            },
            //--------
            //#endregion

            onStageClick(meshName, downKey) {
                if (!meshName) {
                    this.selPart = null;
                    return;
                }

                if (downKey == "Control") {
                    this.selPart = this.d3ModelData.model_param.find(x => x.meshs_name.some(x => x ==
                        meshName));

                    if (!this.selPart) {
                        this.$message.info(`对象【${meshName}】当前未所属任何部件`);
                    }

                } else if (downKey == "Shift") {
                    const object = this.allObjectList.find(x => x.name == meshName);

                    if (object) this.onObjectClick(object);
                }


            },

            onControlsChange() {
                if (this.selPart) {
                    this._selPart = this.selPart;
                    this.selPart = null;
                }
            },

            onControlsEnd() {
                if (this._selPart) {
                    this.selPart = this._selPart;
                    this._selPart = null;
                }
            },


            //#region 左下边工具方法
            //--------

            onShowTip() {
                this.$message({
                    message: `功能开发中，敬请期待`,
                    type: 'info'
                });
            },

            onResetView() {
                this.stage.resetView();
            },

            onHideSet() {
                this.hideSet = !this.hideSet;

                const isSetObjects = this.allObjectList.filter(x => x.belongPartName != "");

                if (this.hideSet) {
                    const hideNames = isSetObjects.map(x => x.name);
                    this.stage.hideMeshs(hideNames);
                } else {

                    const showNames = isSetObjects.reduce((acc, x) => {
                        const part = this.d3ModelData.model_param.find(y => y.name == x.belongPartName);
                        if (!part.visible) acc.push(x.name);
                        return acc;
                    }, []);

                    this.stage.showMeshs(showNames)
                }
            },

            //--------
            //#endregion

            //#region 右下边贴图uv方法
            //--------

            onDefaultOffset() {
                this.selPart.map_param.angle = this.selPart.map_param_ori.angle;
                this.selPart.map_param.offset.x = this.selPart.map_param_ori.offset.x;
                this.selPart.map_param.offset.y = this.selPart.map_param_ori.offset.y;
            },

            onDefaultScale() {
                this.selPart.map_param.scale.x = this.selPart.map_param_ori.scale.x;
                this.selPart.map_param.scale.y = this.selPart.map_param_ori.scale.y;
            },

            //--------
            //#endregion



            //#region 右边属性方法
            //--------

            removeMeshName(meshName, index) {
                this.onMouseLeave(meshName);

                const object = this.allObjectList.find(x => x.name == meshName);
                if (object) object.belongPartName = "";

                this.selPart.meshs_name.splice(index, 1);

                this.stage.clearPartColor([meshName]);
                this.stage.clearPartColorDetail([meshName]);
                this.stage.partNeedUpdate([meshName]);
                this.stage.showMeshs([meshName]);
            },

            //--------
            //#endregion



            //#region 右边材质贴图方法
            //--------

            onMatEnter(item) {
                this.enterColor = item;
            },

            onMatLeave() {
                this.enterColor = null;
            },

            async onColorClick(item) {
                this.selPart.cur_color = item;

                await this.updateMat(this.selPart, this.selPart.meshs_name, item);

                this.$forceUpdate();
            },

            onAddMat(isMulti, list, selectedList) {
                this.matSelMulti = isMulti;
                this.matSelList = [...list].filter(x => x);
                this.matSelected = [...selectedList].filter(x => x);
                this.matVisible = true;
            },

            onClickMat(item) {
                if (this.matSelMulti) {
                    const index = this.matSelList.findIndex(x => x.id == item.id);
                    index >= 0 ? this.matSelList.splice(index, 1) : this.matSelList.push(item);
                } else {
                    this.matSelList.pop();
                    this.matSelList.push(item)
                }
            },

            async onDelMat(item, index) {
                const res = await this.clearPlanByColor(item);

                if (!res) return;

                this.selPart.select_color.splice(index, 1);
                if (this.selPart.cur_color?.id == item.id) {
                    this.selPart.cur_color = null;
                    this.stage.clearPartColor(this.selPart.meshs_name);
                    this.stage.clearPartColorDetail(this.selPart.meshs_name);
                    this.stage.partNeedUpdate(this.selPart.meshs_name);
                }
            },

            onExchangeMat(item, index) {
                if (this.selPart.default_color) {
                    const defaultColor = JSON.parse(JSON.stringify(this.selPart.default_color));
                    this.selPart.default_color = item;
                    this.selPart.select_color[index] = defaultColor;
                } else {
                    this.selPart.default_color = item;
                    this.selPart.select_color.splice(index, 1);
                }
            },

            onClearSelColor() {

                if (this.selPart.select_color.some(x => x.id == this.selPart.cur_color?.id)) {
                    this.selPart.cur_color = null;
                    this.stage.clearPartColor(this.selPart.meshs_name);
                    this.stage.clearPartColorDetail(this.selPart.meshs_name);
                    this.stage.partNeedUpdate(this.selPart.meshs_name);
                }

                this.selPart.select_color.length = 0;

                this.$forceUpdate();
            },

            onDbClickMat(item) {
                if (this.matSelMulti) return;
                this.onMatSubmit();
            },

            onMatCancel() {
                this.matVisible = false;
            },

            async onMatSubmit() {

                let res = true;

                if (this.matSelMulti) {
                    this.selPart.select_color = this.matSelList.map(x => this.deepClone(x));
                    if (this.selPart.select_color.some(x => x.id == this.selPart.default_color?.id)) {
                        this.selPart.default_color = null;
                    }
                } else {

                    res = await this.clearPlanByColor(this.selPart.default_color);

                    if (!res) return;

                    this.selPart.default_color = this.deepClone(this.matSelList[0]);
                    if (this.selPart.select_color.some(x => x.id == this.selPart.default_color?.id)) {
                        const index = this.selPart.select_color.findIndex(x => x.id == this.selPart
                            .default_color.id);
                        if (index >= 0) this.selPart.select_color.splice(index, 1);
                    }
                }

                if (!res) return;

                const legalCurColor = [this.selPart.default_color, ...this.selPart.select_color].some(x => x
                    ?.id == this.selPart.cur_color?.id);

                if (!this.selPart.cur_color || !legalCurColor) {
                    this.selPart.cur_color = this.matSelMulti ? this.selPart.select_color[0] : this.selPart
                        .default_color;

                    await this.updateMat(this.selPart, this.selPart.meshs_name, this.selPart.cur_color);
                }

                this.matVisible = false;

            },

            onSetDetailAll() {
                const {
                    roughness,
                    metalness,
                    opacity
                } = this.selPart.default_color;

                this.selPart.select_color.map(color => {
                    color.roughness = roughness;
                    color.metalness = metalness;
                    color.opacity = opacity;
                })

                this.$message.success("应用成功");
            },

            // 粗糙度:roughness
            // 金属度:metalness
            // 透明度:opacity
            onChangeMat() {
                this.stage.setPartColorDetail(this.selPart.meshs_name, this.selPart.cur_color);
            },

            async updateMat(part, meshs_name, color) {
                await this.stage.setPartColor(meshs_name, color);
                this.stage.setPartColorDetail(meshs_name, color);
                this.stage.setMapDetail(part);
                this.stage.partNeedUpdate(meshs_name);
            },

            //--------
            //#endregion


            //#region api
            //--------

            getMaterialType() {
                return new Promise((resolve, reject) => {
                    this.$http.get("{!! yzWebFullUrl('plugin.supplier.supplier.controllers.color.color-category.get-categorys-json') !!}").then(response => {
                            if (response.body.result == 1) {
                                this.matTypeList = response.body.data;
                                resolve(true);
                            } else {
                                this.$message.error(response.data.msg);
                                reject(response.data.msg);
                            }
                        }),
                        function(res) {
                            reject(res);
                        };
                })

            },

            getMaterial() {
                return new Promise((resolve, reject) => {
                    if (this.getMatTimeout) {
                        clearTimeout(this.getMatTimeout);
                        this.getMatTimeout = null;
                    }

                    this.getMatTimeout = setTimeout(async () => {
                        this.matSearchParam.search.cate_id = this.matTypeLevel2Id ? this
                            .matTypeLevel2Id : this.matTypeLevel1Id ? this.matTypeLevel1Id :
                            null;

                        const response = await this.$http.post("{!! yzWebFullUrl('plugin.supplier.supplier.controllers.color.texture-map.index') !!}",
                            this
                            .matSearchParam);

                        if (response.body.result === 1) {
                            const data = response.body.data;
                            if (data) {
                                console.log("===materialList===", data);
                                data.map(x => {
                                    x.roughness = parseFloat(x.belongs_to_uv
                                        ?.roughness) || 0;
                                    x.metalness = parseFloat(x.belongs_to_uv
                                        ?.metalness) || 0;
                                    x.opacity = parseFloat(x.belongs_to_uv
                                        ?.opacity) || 1;
                                })

                                this.matList = data;
                            }
                            resolve(true);
                        } else {
                            this.$message.error(response.data.msg);
                            reject(response.data.msg)
                        }
                    }, 200);
                })


            },

            //--------
            //#endregion

            stageDispose() {
                this.stage.dispose();
                this.stage = null;
            },

            checkIfDraggable(evt) {
                // 非数组元素，返回 false 阻止拖拽
                return evt?.draggedContext?.index <= this.selPart.select_color.length - 1;
            },


            async oneKey() {
                await this.newPart(true);

                if (this.matList[0]) {
                    this.selPart.default_color = this.deepClone(this.matList[0]);
                    for (let i = 1; i < 9; i++) {
                        const color = this.matList[i];
                        if (color) this.selPart.select_color.push(this.deepClone(color));
                    }
                }

                this.objectList.map(item => {
                    this.onObjectClick(item);
                })

                this.onSure();


            },

            async oneKey2() {

                await this.onPartByObject();

                this.d3ModelData.model_param.map(part => {
                    if (this.matList[0]) {
                        part.default_color = this.deepClone(this.matList[0]);
                        for (let i = 1; i < 9; i++) {
                            const color = this.matList[i];
                            if (color) part.select_color.push(this.deepClone(color));
                        }
                    }
                })

                this.onSure();
            },

            //#region slim


            onSelSlimObject(item) {
                this.modelSlim.selObject = item;
                this.hoverNames = item ? [item.name] : [];
            },

            onWireFrame() {
                const meshList = this.stage.getObjects();
                meshList.map(x => x.material.wireframe = this.modelSlim.wireframe);
            },

            async onAllSlim(value) {
                try {
                    this.modelSlim.loading = true;
                    this.modelSlim.loadingText = "模型正在做减面处理，请稍等...";
                    this.modelSlim.timeout = setTimeout(() => {
                        this.modelSlim.loadingText = "模型面数较多，请耐心等待处理...";
                    }, 2000);

                    for (let object of this.modelSlim.objects) {
                        object.rate = value;
                        await this.stage.slimObject(object);
                    }
                    this.$forceUpdate();
                } finally {
                    this.modelSlim.loading = false;
                    clearTimeout(this.modelSlim.timeout);
                    this.modelSlim.timeout = null;
                }
            },

            async onObjectSlim(value) {

                try {
                    this.modelSlim.loading = true;
                    this.modelSlim.loadingText = this.modelSlim.selObject.name + "正在做减面处理，请稍等...";
                    this.modelSlim.timeout = setTimeout(() => {
                        this.modelSlim.loadingText = this.modelSlim.selObject.name +
                            "面数较多，请耐心等待处理...";
                    }, 2000);

                    await this.stage.slimObject(this.modelSlim.selObject);
                    this.$forceUpdate();
                } finally {
                    this.modelSlim.loading = false;
                }

            },

            async onOneKeySlim() {
                try {

                    const allFaces = this.modelSlim.objects.reduce((acc, x) => {
                        acc += x.allFaces;
                        return acc;
                    }, 0)

                    console.log("onekey allFaces => ", allFaces)

                    const fn = async () => {
                        this.modelSlim.rate = allFaces < 10000 ?
                            0.5 :
                            parseFloat(((allFaces - 5000) / allFaces).toFixed(3)) || 0.5;

                        this.modelSlim.loading = true;
                        this.modelSlim.loadingText = "正在做一键减面处理，请稍等...";
                        this.modelSlim.timeout = setTimeout(() => {
                            this.modelSlim.loadingText = "模型面数较多，请耐心等待处理...";
                        }, 2000);

                        await this.onAllSlim(this.modelSlim.rate);

                        this.$forceUpdate();
                    }

                    if (allFaces > 100000) {
                        this.$confirm('当前模型面数超10万，一键处理耗时较长且效果可能不理想，建议分对象逐个减面处理', '温馨提示', {
                            confirmButtonText: '听从建议',
                            cancelButtonText: '坚持一键减面',
                            type: 'warning'
                        }).then(() => {
                            // done();
                        }).catch(async () => {
                            await fn();
                        });
                    } else {
                        await fn();
                    }



                } finally {
                    this.modelSlim.loading = false;
                }
            },

            async onSlimChange(file, fileList) {
                try {
                    this.modelSlim.loading = true;
                    this.modelSlim.oriModelData = file.raw;
                    this.modelSlim.oriModel = await this.stage.loadModelSlimByFile(file.raw);
                    const objects = this.stage.getObjects();
                    const oriNames = JSON.stringify(this.modelSlim.objects.map(x => x.name));
                    const curNames = JSON.stringify(objects.map(x => x.name))
                    if (oriNames != curNames) {
                        this.$message.error("减面模型与源模型的对象不匹配，请重新上传");
                        this.stage.delSlim();
                        this.modelSlim.objects.map(x => x.curFaces = 0);
                        return;
                    }

                    this.modelSlim.objects.map(x => {
                        x.curFaces = this.stage.getObjectFacesByName(x.name);
                    })
                } finally {
                    this.modelSlim.loading = false;
                }
            },

            onSlimDel() {
                this.stage.delSlim();
                this.modelSlim.oriModel = null;
            },

            onSlimResetFace() {
                this.modelSlim.total_face = this.d3ModelData.total_face || 0;
            },

            onSlimCancel() {
                // this.onSlimReset();
                // this.onSlimRollBack();

                this.modelSlim.total_face = this.modelSlim_total_face || 0;
                this.onSlimReset();

            },

            async onSlimSubmit() {
                // if (!this.modelSlim.oriModel) this.modelSlim.oriModelData = null;
                // this.modelSlim.isDeal = this.modelSlim.oriModel ? true : false;
                // this.onSlimReset();

                this.onSlimReset();
            },

            onSlimRecord() {
                // if (this.modelSlim.oriModel) {
                //     this.modelSlimOriBatData = this.modelSlim.oriModelData;
                //     this.modelSlimOriBat = this.modelSlim.oriModel.clone();
                // } else {
                //     this.modelSlimOriBatData = null;
                //     this.modelSlimOriBat = null;
                // }

                this.modelSlim_total_face = this.modelSlim.total_face;

            },

            onSlimRollBack() {
                this.modelSlim.oriModelData = this.modelSlimOriBatData;
                this.modelSlim.oriModel = this.modelSlimOriBat;
                this.stage.rollbackSlim();
            },

            onSlimReset() {
                this.modelSlim.visible = false;
                this.modelSlim.wireframe = false;
                this.modelSlim.selObject = null;

                this.hoverNames = [];
                this.onWireFrame();
            },

            //#endregion

            //#region colorPlan
            //--------
            onColorPlanShow(plan) {
                this.syncPartColorList();
                this.colorPlan.visible = true;
                this.colorPlan.recordData = JSON.parse(JSON.stringify(this.colorPlan.data));
                this.colorPlan.sel = plan || this.colorPlan.data[0];
                this.$nextTick(() => {
                    this.scrollToPlan(this.$refs.planScrollRef.$el, this.colorPlan.sel, -180);
                })

            },
            syncPartColorList() {
                for (let plan of this.colorPlan.data) {
                    for (let part of plan.partList) {
                        const oriPart = this.d3ModelData.model_param.find(x => x.uuid == part.uuid);
                        if (!oriPart) continue;
                        part.colorList = [oriPart.default_color, ...oriPart.select_color];
                    }
                }
            },
            createColorPlan() {
                let isLegal = true;

                const plan = {
                    uuid: this.stage.createUUID(),
                    name: "",
                    partList: []
                }

                if (!this.d3ModelData.model_param.length) {
                    isLegal = false;
                    this.$message.error("创建失败，没有部件");
                }

                for (let part of this.d3ModelData.model_param) {
                    if (part.changeLock == 0 || part.visible == 1) continue;

                    if (!part.default_color) {
                        isLegal = false;
                        this.$message.error(`创建失败，部件【${part.name}】没有默认颜色`);
                        break;
                    }

                    plan.partList.push({
                        uuid: part.uuid,
                        name: part.name,
                        colorList: [part.default_color, ...part.select_color],
                        sel: part.default_color,
                    })
                }

                if (isLegal) {
                    this.colorPlan.data.push(plan);
                    this.colorPlan.sel = plan;
                    this.$nextTick(() => {
                        this.scrollToRight(this.$refs.planScrollRef.$el);
                    })
                }
            },

            scrollToRight(container) {
                if (container) {
                    container.scrollLeft = container.scrollWidth - container.clientWidth;
                }
            },

            scrollToPlan(container, plan, offsetX) {
                if (!container || !plan) return;

                let targetDom = this.$refs['planRef_' + plan.uuid];

                if (Array.isArray(targetDom)) targetDom = targetDom[0];

                if (!targetDom) {
                    console.warn("scrollToPlan 未找到目标 dom");
                    return;
                }

                let relativeX = 0;

                if (container === targetDom.offsetParent) {
                    relativeX = element.offsetLeft;
                } else {
                    console.log(targetDom, container)
                    const elemRect = targetDom.getBoundingClientRect();
                    const parentRect = container.getBoundingClientRect();

                    relativeX = elemRect.left - parentRect.left;
                }

                container.scrollLeft = relativeX + offsetX;
            },

            selectColorPlan(item) {
                this.colorPlan.sel = item;
            },

            async onPlanDisplay(plan) {
                let isSuccess = true;

                for (let part of plan.partList) {
                    const oriPart = this.d3ModelData.model_param.find(x => x.uuid == part.uuid);
                    if (!oriPart) {
                        isSuccess = false;
                        continue;
                    }
                    await this.updateMat(oriPart, part.name, part.sel);
                }

                if (isSuccess) this.$message.success("一键换装成功")
            },

            deleteColorPlan(item) {
                const index = this.colorPlan.data.indexOf(item);
                this.colorPlan.data.splice(index, 1);
                if (this.colorPlan.sel == item) {
                    const selIndex = index == 0 ? index : index - 1;
                    this.colorPlan.sel = this.colorPlan.data[selIndex];
                }
            },

            selectPlanPartColor(part, color) {
                part.sel = color;
            },

            onColorPlanCancel() {
                this.colorPlan.data = this.colorPlan.recordData;
                this.colorPlan.sel = null;
                this.colorPlan.visible = false;
            },

            onColorPlanSure() {
                this.colorPlan.visible = false;
                this.colorPlan.sel = null;
            },

            planIncludesPart(part) {
                const allPartUUID = this.colorPlan.data.reduce((acc, plan) => {
                    acc = [...acc, ...plan.partList.map(part => part.name)];
                    return acc;
                }, [])
                return allPartUUID.includes(part.name);
            },

            planFilterPart(part) {
                return this.colorPlan.data.filter(plan => !plan.partList.map(part => part.name).includes(part
                    .name))
            },

            async clearPlanByPart(part) {
                return new Promise((resolve, reject) => {

                    if (!this.planIncludesPart(part)) {
                        resolve(true);
                        return;
                    }

                    this.$confirm('此操作将删除所涉及的配色方案,是否继续?', '提示', {
                        confirmButtonText: '确定',
                        cancelButtonText: '取消',
                        type: 'warning'
                    }).then(() => {
                        this.colorPlan.data = this.planFilterPart(part);
                        resolve(true);
                    }).catch(() => {
                        resolve(false);
                    });
                })
            },

            planIncludesColor(color) {
                const allColorId = this.colorPlan.data.reduce((acc, plan) => {
                    acc = [...acc, ...plan.partList.map(part => part.sel?.id)];
                    return acc;
                }, [])
                return allColorId.includes(color.id);
            },

            planFilterColor(color) {
                return this.colorPlan.data.filter(plan => !plan.partList.map(part => part.sel?.id).includes(
                    color.id))
            },

            async clearPlanByColor(color) {
                return new Promise((resolve, reject) => {

                    if (!color) {
                        resolve(true);
                        return;
                    }

                    if (!this.planIncludesColor(color)) {
                        resolve(true);
                        return;
                    }

                    this.$confirm('此操作将删除所涉及的配色方案,是否继续?', '提示', {
                        confirmButtonText: '确定',
                        cancelButtonText: '取消',
                        type: 'warning'
                    }).then(() => {
                        this.colorPlan.data = this.planFilterColor(color);
                        resolve(true);
                    }).catch(() => {
                        resolve(false);
                    });
                })
            },

            getSaveColorPlan() {

                const res = {
                    data: JSON.parse(JSON.stringify(this.colorPlan.data)),
                    unPassPlan: [],
                }

                for (let plan of res.data) {
                    for (let part of plan.partList) {
                        if (!part.sel) {
                            res.unPassPlan.push(plan.name + "-" + part.name);
                        } else {
                            part.selColorId = part.sel.id;
                            delete part.colorList;
                            delete part.sel;
                        }
                    }
                }

                return res;
            },

            initColorPlan(data) {
                if (!data) return;

                this.colorPlan.data = JSON.parse(JSON.stringify(data));

                for (let plan of this.colorPlan.data) {
                    for (let part of plan.partList) {
                        const oriPart = this.d3ModelData.model_param.find(x => x.name == part.name)
                        if (oriPart) {
                            part.colorList = [oriPart.default_color, ...oriPart.select_color];
                            part.sel = part.colorList.find(x => x.id == part.selColorId);
                            part.uuid = oriPart.uuid;
                            delete part.selColorId;
                        }
                    }
                }

            },

            async clearPlan() {
                return new Promise((resolve, reject) => {
                    if (!this.colorPlan.data.length) {
                        resolve(true);
                        return;
                    }

                    this.$confirm('此操作将清空配色方案,是否继续?', '提示', {
                        confirmButtonText: '确定',
                        cancelButtonText: '取消',
                        type: 'warning'
                    }).then(() => {
                        this.colorPlan.data = [];
                        resolve(true);
                    }).catch(() => {
                        resolve(false);
                    });
                })
            },

            async testNet(time) {
                return new Promise((resovle, reject) => {
                    setTimeout(() => {
                        resovle();
                    }, time);
                })
            },

            getVersion() {
                return "--1.0.0"
            }

            //--------
            //#endregion
        },
        beforeDestroy() {
            this.stageDispose();
        },
    });
</script>

<style lang="scss" scoped>
    .three-dialog {
        width: 100%;
        height: 100%;

        .el-dialog__header {
            padding: 15px;
            height: 60px;
            color: #000000;
            font-weight: 600;

            .el-dialog__close {
                color: #000000;
                font-weight: 600;
                font-size: 18px;

            }
        }

        .el-dialog__body {
            padding: 0;
            height: calc(100% - 60px);
            color: #333333;
        }
    }

    .stage-title {
        display: flex;
        gap: 16px;
        height: 30px;

        .title-text {
            line-height: 30px;
            color: rgba(16, 16, 16, 1);
            font-size: 18px;
        }

        .el-button {
            color: rgba(105, 105, 105, 1);
            font-size: 12px;
            padding: 0px 20px;
        }

    }

    .stage-wrapper {
        width: 100%;
        height: 100%;
        display: flex;
        border-top: #dddddd solid 2px;

        &.disabled {
            opacity: 0.4;
            pointer-events: none;
        }


        .stage-left,
        .stage-right {
            flex: 0 0 auto;
            width: 300px;
            height: 100%;
        }

        .stage-left {
            padding: 10px;

            .left-top,
            .left-bottom {
                width: 100%;
                height: calc(50% - 10px);
            }

            .left-top {

                .left-top-msg {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding-bottom: 15px;
                    height: 40px;
                    border-bottom: #dddddd solid 1px;
                    color: #000000;
                    font-weight: 600;

                    &:first-child {
                        color: rgba(16, 16, 16, 1);
                        font-size: 18px;
                        font-weight: bold;
                    }
                }

                .part-wrapper {
                    height: calc(100% - 40px);
                    margin-top: 10px;

                    .part {
                        display: flex;
                        justify-content: space-between;
                        height: 50px;
                        padding: 10px;
                        border-radius: 5px;
                        cursor: pointer;

                        .part-name {
                            max-width: 180px;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        }

                        .el-input {
                            .el-input__inner {
                                border: none;
                                padding: 0px;
                                width: 140px;
                                height: 30px;
                                background-color: transparent;
                            }

                        }

                        .funcs {
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            gap: 12px;

                            .count {
                                background-color: #1785F6;
                                max-width: 30px;
                                min-width: 18px;
                                height: 18px;
                                border-radius: 4px;
                                color: #fff;
                                text-align: center;
                                line-height: 18px;
                                cursor: default;
                            }

                            .func {
                                width: 18px;
                                height: 18px;
                                cursor: pointer;

                                i {
                                    font-size: 16px;
                                    width: 100%;
                                    height: 100%;
                                }

                                &:active {
                                    scale: 0.9;
                                }

                            }



                        }

                        &:hover {
                            background-color: #eeeeee;
                        }

                        &.sel {
                            background-color: #dddddd;
                        }
                    }

                    .import-part {
                        width: 100%;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                    }
                }
            }

            .left-bottom {
                margin-top: 20px;

                .left-bottom-msg {
                    height: 40px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    font-size: 12px;
                    color: #000000;
                    font-weight: 600;
                }

                .object-wrapper {
                    height: calc(100% - 70px);

                    .object {
                        display: flex;
                        gap: 10px;
                        padding-left: 10px;
                        align-items: center;
                        user-select: none;
                        cursor: pointer;
                        padding: 5px 5px;
                        border-radius: 5px;

                        :nth-child(2) {
                            /* max-width: calc(100% - 70px); */
                            max-width: 100%;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        }

                        &:hover {
                            background-color: #eeeeee;
                        }

                        &:active {
                            background-color: #dddddd;
                        }
                    }
                }
            }
        }

        .stage-center {
            height: 100%;
            width: calc(100vw - 600px);
            background-color: #eeeeee;
            border-left: #dddddd solid 2px;
            border-right: #dddddd solid 2px;
            box-sizing: content-box;
            position: relative;

            .map {
                width: 100%;
                height: 100%;
                overflow: hidden;
            }

            .version {
                color: #999999;
                font-size: 14px;
                position: absolute;
                left: 20px;
                top: 20px;
                user-select: none;
            }

            .stage-tool-bottom {
                position: absolute;
                left: 50px;
                bottom: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 20px;

                .stage-tool {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-direction: column;
                    gap: 10px;

                    i {
                        width: 40px;
                        height: 40px;
                        font-size: 40px;
                        text-align: center;
                        line-height: 40px;
                        color: rgba(16, 16, 16, 1);
                    }

                    p {
                        height: 20px;
                        line-height: 20px;
                        color: rgba(16, 16, 16, 1);
                        font-size: 14px;
                    }

                    &:not(:first-child) {
                        cursor: pointer;

                        &:active {
                            scale: 0.9;
                        }
                    }
                }





            }

            .part-map {
                position: absolute;
                right: 2px;
                bottom: 2px;
                width: 300px;
                height: 550px;
                background-color: #ffffff;

                box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);

                .map-title {
                    width: 100%;
                    height: 45px;
                    background-color: rgba(247, 247, 247, 1);

                    line-height: 45px;
                    color: rgba(16, 16, 16, 1);
                    font-size: 18px;
                    font-weight: bold;
                    padding: 5px 10px;
                }

                .map-param {
                    margin-top: 10px;
                    padding: 0px 10px;

                    .param-title {
                        height: 20px;
                        line-height: 20px;
                        color: rgba(16, 16, 16, 1);
                        font-size: 14px;
                    }

                    .param-wrapper {
                        padding: 0px 10px;
                        width: 100%;
                        display: flex;


                        .el-slider {
                            flex: 1 1 auto;

                            .el-slider__input {
                                width: 70px;
                            }

                            .el-slider__runway {
                                width: calc(100% - 80px);
                            }

                            .el-input-number__decrease,
                            .el-input-number__increase {
                                display: none;
                            }

                            .el-input-number .el-input__inner {
                                padding-left: 10px;
                                padding-right: 0px;
                                text-align: start;
                            }
                        }

                        span {
                            width: 16px;
                            text-align: end;
                            line-height: 38px;
                        }
                    }
                }

                .map-clear {
                    margin-top: 10px;
                    padding: 0px 10px;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    flex-direction: row-reverse;
                }
            }

            .gui {
                position: absolute;
                right: 2px;
                top: 2px;
                width: 300px;
                background-color: #ffffff;
                padding: 0px 10px;
                box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
            }
        }

        .stage-right {

            display: flex;
            justify-content: space-between;
            flex-direction: column;
            padding-bottom: 10px;

            .right-content {
                padding: 0px 10px;
                flex: 1 1 auto;

                .right-title {
                    color: #000000;
                    font-weight: 600;
                    font-size: 16px;
                    margin-bottom: 10px;
                    background-color: rgba(247, 247, 247, 1);
                    padding: 5px 10px;
                    margin: 10px -10px 0px -10px;

                }

                .right-name,
                .right-color,
                .right-visible {
                    display: flex;
                    justify-content: space-between;
                    padding: 5px 0px;
                }

                .right-name {
                    :nth-child(1) {
                        min-width: 80px;
                        line-height: 28px;
                    }
                }

                .right-mesh {
                    margin-top: 5px;

                    .mesh-rect {
                        margin-top: 5px;
                        height: 140px;
                        border: #dddddd solid 1px;
                        border-radius: 10px;
                        padding: 5px;

                        .mesh-wrapper {
                            height: 100%;

                            .mesh {
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                padding: 2px 5px;
                                border-radius: 5px;

                                :nth-child(1) {
                                    width: calc(100% - 20px);
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                    white-space: nowrap;
                                    user-select: none;
                                }

                                :nth-child(2) {
                                    width: 16px;
                                    height: 16px;
                                    cursor: pointer;
                                    display: flex;
                                    justify-content: center;

                                    i {
                                        font-size: 14px;
                                        width: 100%;
                                        height: 100%;
                                    }

                                    &:active {
                                        scale: 0.9;
                                    }
                                }

                                &:hover {
                                    background-color: #eeeeee;
                                }
                            }
                        }
                    }
                }

                .mat-default-wrapper {
                    margin-top: 10px;

                    .mat-default {
                        margin-top: 5px;
                        display: flex;
                        align-items: flex-end;
                        gap: 5px;
                        padding-left: 5px;

                        .mat-add,
                        .mat-color {
                            width: 100px;
                            height: 100px;

                        }

                        .mat-msg {
                            padding-left: 5px;
                            display: flex;
                            flex-direction: column;
                            align-items: flex-start;

                            div {
                                width: 160px;
                                text-decoration: none;
                                text-overflow: ellipsis;
                                white-space: nowrap;
                                overflow: hidden;
                            }
                        }
                    }
                }

                .mat-more {
                    margin-top: 10px;

                    .more-rect {
                        margin-top: 5px;
                        border: #dddddd solid 1px;
                        border-radius: 10px;
                        padding: 5px;

                        .more-wrapper {
                            display: flex;
                            justify-content: flex-start;
                            align-items: center;
                            flex-wrap: wrap;
                            gap: 10px;
                            max-height: 300px;
                            padding: 7px;

                            .mat-add,
                            .mat-color {
                                width: 55px;
                                height: 55px;
                            }

                            .horizontal-list {
                                display: flex;
                                justify-content: flex-start;
                                align-items: center;
                                flex-wrap: wrap;
                                gap: 10px;
                                max-height: 300px;
                            }
                        }

                        .more-clear {
                            margin-top: 10px;
                            width: 100%;
                            display: flex;
                            justify-content: flex-end;

                        }
                    }
                }

                .mat-add {
                    border: #dddddd solid 1px;
                    border-radius: 10px;
                    position: relative;
                    cursor: pointer;

                    &::after {
                        content: "✚";
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate3d(-50%, -50%, 0);
                        font-size: 18px;
                        font-weight: 100;
                        color: #aaa;
                    }

                    &:active {
                        scale: 0.9;
                    }
                }

                .mat-color {
                    border: #dddddd solid 1px;
                    border-radius: 5px;
                    position: relative;
                    cursor: pointer;

                    img {
                        width: 100%;
                        height: 100%;
                        border-radius: 5px;
                    }

                    &.sel {
                        &::after {
                            content: "";
                            position: absolute;
                            left: -5px;
                            top: -5px;
                            width: calc(100% + 10px);
                            height: calc(100% + 10px);
                            background-color: transparent;
                            border: solid 1px #aaaaaa;
                            border-radius: 10px;
                        }
                    }

                    .update-color {
                        position: absolute;
                        z-index: 1;
                        width: 100%;
                        height: 20px;
                        color: #ffffff;
                        background-color: rgba(16, 16, 16, 0.5);
                        font-size: 12px;

                        left: 0px;
                        bottom: 0px;
                        border-radius: 0px 0px 5px 5px;
                        line-height: 20px;
                        text-align: center;

                        &.show {
                            animation: fade-in 0.3s ease-in forwards;
                        }

                        &.hide {
                            opacity: 0;
                            pointer-events: none;
                        }
                    }

                    .del-color {
                        position: absolute;
                        z-index: 1;
                        width: 15px;
                        height: 15px;
                        color: #ffffff;
                        background-color: rgba(16, 16, 16, 0.5);

                        right: 0px;
                        top: 0px;
                        border-radius: 0px 5px 0px 15px;
                        line-height: 15px;
                        text-align: center;
                        font-size: 8px;
                        font-weight: 100;
                        padding-left: 2px;

                        &.show {
                            animation: fade-in 0.3s ease-in forwards;
                        }

                        &.hide {
                            opacity: 0;
                            pointer-events: none;
                        }
                    }

                    .exchange-color {
                        position: absolute;
                        z-index: 1;
                        width: 15px;
                        height: 15px;
                        color: #ffffff;
                        background-color: rgba(16, 16, 16, 0.5);

                        left: 0px;
                        top: 0px;
                        border-radius: 5px 0px 15px 0px;
                        line-height: 15px;
                        text-align: center;
                        font-size: 8px;
                        font-weight: 100;
                        padding-right: 2px;

                        &.show {
                            animation: fade-in 0.3s ease-in forwards;
                        }

                        &.hide {
                            opacity: 0;
                            pointer-events: none;
                        }
                    }

                    .color-name {
                        position: absolute;
                        z-index: 1;
                        width: 100%;
                        height: 16px;
                        color: #ffffff;
                        background-color: rgba(16, 16, 16, 0.5);

                        left: 0px;
                        bottom: 0px;
                        /* border-radius: 0px 5px 0px 15px; */
                        line-height: 16px;
                        text-align: center;
                        font-size: 10px;
                        font-weight: 100;
                        /* padding-left: 2px; */

                        text-decoration: none;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                        overflow: hidden;
                    }
                }

                .detail-title-wrapper {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .color-detail {

                    .detail-title {
                        height: 20px;
                        line-height: 20px;
                        color: rgba(16, 16, 16, 1);
                        font-size: 14px;
                    }

                    .detail-wrapper {
                        padding: 0px 10px;
                        width: 100%;


                        .el-slider {

                            .el-slider__input {
                                width: 70px;

                            }

                            .el-slider__runway {
                                width: calc(100% - 80px);
                            }

                            .el-input-number__decrease,
                            .el-input-number__increase {
                                display: none;
                            }

                            .el-input-number .el-input__inner {
                                padding-left: 10px;
                                padding-right: 0px;
                                text-align: start;
                            }
                        }
                    }
                }
            }

            .right-footer {
                padding: 10px;
                flex: 0 0 auto;
                height: 40px;
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;

                :nth-child(1) {
                    width: 100%;
                }
            }
        }
    }


    .control-pop {
        .control-item {
            height: 30px;
            padding: 5px 0px;
            display: flex;

            i {
                line-height: 20px;
                font-size: 18px;
            }

            p {
                margin-left: 5px;
                line-height: 20px;
                font-size: 14px;
            }
        }
    }

    .mat-dialog {
        .mat-top {
            padding: 10px;

            .mat-select {
                margin-top: 10px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 10px;

            }
        }

        .mat-wrapper {
            height: 400px;
            border: #dddddd solid 2px;
            padding: 10px;

            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;

            .mat {
                width: 120px;
                height: 140px;
                user-select: none;
                cursor: pointer;


                .png {
                    width: 120px;
                    height: 120px;

                    border: #dddddd solid 2px;

                    img {
                        width: 100%;
                        height: 100%;
                    }
                }

                .name {
                    height: 20px;
                    line-height: 20px;
                    text-align: center;

                    max-width: 100%;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;

                    font-size: 12px;
                }

                &:active {
                    .png {
                        scale: 0.9;
                    }
                }

                &.sel,
                &.seled {
                    .png {
                        position: relative;
                        border: rgba(105, 105, 105, 1) solid 2px;

                        &::after {
                            content: "✔";
                            position: absolute;
                            left: 0px;
                            bottom: 0px;
                            width: 30px;
                            height: 20px;
                            font-size: 18px;
                            font-weight: 100;
                            color: #ffffff;
                            border-radius: 0 10px 0 0;
                            text-align: center;
                            line-height: 20px;
                            background-color: rgba(105, 105, 105, 1);
                            z-index: 2;
                        }
                    }
                }

                &.sel {
                    .png {
                        border: #2ab27b solid 2px !important;

                        &::after {
                            background-color: #2ab27b !important;
                        }
                    }
                }

            }
        }
    }


    .scrollable-content {
        overflow-x: hidden;
        overflow-y: auto;
        /* 启用垂直滚动 */
    }

    /* 自定义滚动条样式 */
    .scrollable-content::-webkit-scrollbar {
        width: 4px;
        /* 滚动条宽度 */
    }

    .scrollable-content::-webkit-scrollbar-track {
        background: #f1f1f1;
        /* 滚动条轨道颜色 */
    }

    .scrollable-content::-webkit-scrollbar-thumb {
        background: #aaa;
        /* 滚动条颜色 */
        border-radius: 2px;
        /* 滚动条圆角 */
    }

    .scrollable-content::-webkit-scrollbar-thumb:hover {
        background: #555;
        /* 滚动条悬停颜色 */
    }

    @keyframes fade-in {
        0% {
            opacity: 0;
            pointer-events: none;
        }

        100% {
            opacity: 1;
            pointer-events: auto;
        }
    }

    .mc-title {
        font-weight: bold;
        color: #333333;
        font-size: 18px;
        margin-top: 10px;
        border-bottom: solid 1px #dddddd;

        &:not(:first-child) {
            margin-top: 20px;
        }

        span {
            margin-left: 5px;
            font-size: 14px;
            color: #aaaaaa;
        }
    }

    .mc-item {
        padding-left: 20px;

        span {
            font-weight: bold;
            margin: 0 5px;
        }

        .success {
            color: green;
        }

        .warn {
            color: orangered;
        }

        .error {
            color: red;
        }

        .info {
            color: #aaaaaa;
        }
    }

    .slim-container {
        width: 100%;
        height: calc(90vh - 74px - 40px);
        display: flex;
        gap: 10px;

        .slim-right {
            width: 400px;
            height: 100%;

            .part-wrapper {
                height: calc(70% - 50px);
                overflow-y: auto;
            }

            .part {
                display: flex;
                justify-content: space-between;
                height: 50px;
                padding: 10px;
                border-radius: 5px;
                cursor: pointer;


                &:hover {
                    background-color: #eeeeee;
                }

                &.sel {
                    background-color: #dddddd;
                }

                .all-faces {
                    color: #aaaaaa;
                }

                .retain-faces {
                    color: green;
                }
            }

            .part-title {
                pointer-events: none;
                background-color: #000000;
                color: #ffffff;
            }

            .tip-wrapper {
                margin-top: 10px;
                width: 100%;
                height: 30px;
                display: flex;
                gap: 10px;
                align-items: center;

                i {
                    font-size: 24px;
                }
            }

            .func-wrapper {
                margin-top: 10px;
                height: calc(30% - 40px - 40px - 40px);
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                rap: 5px;

                .func {
                    height: 50px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;

                    .title {
                        user-select: none;
                    }

                    .slider {
                        width: 300px;
                        margin-right: 20px;
                        margin-bottom: 0px !important;
                    }

                    .oneKey {
                        width: 100%;
                        height: 40px;
                        padding: 0;
                        display: flex;
                        justify-content: center;
                        align-content: center;

                        span {
                            line-height: 40px;
                        }
                    }
                }

                .face {
                    width: 100%;
                    height: 50px;

                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 20px;

                    .title {
                        flex: 0 0 auto;
                    }

                    .slider {
                        flex: 1 1 auto;
                        margin-bottom: 0px;
                        /* width: 200px; */
                    }

                    .input {
                        width: 80px;
                        height: 30px;
                        display: flex;
                        justify-content: center;
                        align-items: center;

                        .el-input__inner {
                            padding: 0px;
                            height: 30px;
                        }
                    }

                }
            }

            .dialog-footer {
                margin-top: 10px;
                display: flex;
                flex-direction: row-reverse;
                gap: 20px;
                height: 40px;

                .el-button {
                    width: 100%;
                    margin-left: 0px;
                }
            }
        }

        .slim-map {
            width: calc(100% - 400px);
            height: 100%;
        }
    }

    .plan-list {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 20px;

        .plan-list-scroll {
            height: 63px;
            padding: 10px;
            display: inline-flex;
            align-content: flex-start;
            gap: 20px;
            overflow-x: auto;
            scroll-behavior: smooth;
        }

        .plan {
            width: 160px;
            height: 40px;
            position: relative;

            &.sel {
                background: #dddddd;

                .input {
                    .el-input__inner {
                        border: #F68517 1px solid !important;
                    }

                }
            }


            .input {
                width: 160px;
                height: 40px;
            }

            .close {
                width: 20px;
                height: 20px;
                position: absolute;
                top: -10px;
                right: -10px;
                display: flex;
                font-size: 18px;
            }
        }

        .btn-add {
            flex: 0 0 auto;
            width: 160px;
            height: 40px;
        }


    }

    .part-list {
        margin-top: 10px;
        width: 100%;
        height: 500px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        overflow-y: auto;
        padding: 10px;

        .part {
            width: 100%;
            height: 160px;
            border-radius: 10px;
            background-color: rgba(255, 255, 255, 1);
            border: 1px solid;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 10px 10px 0px 10px;

            .name {
                color: #111111;
                font-size: 14px;
            }

            .color-list {
                display: flex;
                overflow-x: auto;
                padding-bottom: 10px;

                .color {
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-content: center;
                    gap: 10px;

                    &.sel {
                        .thumb {
                            border: 2px solid rgba(41, 186, 156, 1);
                        }
                    }

                    .thumb {
                        width: 70px;
                        height: 70px;
                        border-radius: 10px;
                        background-color: rgba(255, 255, 255, 1);
                        border: 2px solid rgba(41, 186, 156, 0);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        cursor: pointer;

                        img {
                            border-radius: 5px;
                            width: 60px;
                            height: 60px;
                            object-fit: cover;
                        }
                    }

                    .text {
                        width: 70px;
                        text-decoration: none;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                        overflow: hidden;
                        text-align: center;
                        font-size: 12px;
                    }
                }
            }

        }
    }

    .plan-list-outside {
        margin-top: 20px;
        display: flex;
        flex-direction: column;
        gap: 20px;

        .plan-outside {
            width: 100%;
            border-radius: 10px;
            background-color: rgba(255, 255, 255, 1);
            border: 1px solid rgba(187, 187, 187, 1);
            padding: 10px;
            position: relative;
            cursor: pointer;

            .plan-outside-title {
                display: flex;
                align-items: center;
                gap: 10px;

                i {
                    font-size: 16px;

                    &:hover {
                        color: #2ab27b;
                    }

                    &:active {
                        scale: 0.9;
                    }
                }

                .name {
                    height: 20px;
                    line-height: 20px;
                    color: rgba(16, 16, 16, 1);
                    font-size: 14px;
                }
            }



            .close {
                position: absolute;
                top: 10px;
                right: 10px;
                font-size: 18px;
                cursor: pointer;

                &:active {
                    scale: 0.9;
                }
            }

            .part-list-outside {
                margin-top: 10px;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;

                .part {
                    width: 55px;
                    height: 55px;
                    border-radius: 5px;
                    overflow: hidden;

                    img {
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                    }
                }
            }
        }
    }

    /*滚动条*/
    ::-webkit-scrollbar {
        width: 3px;
        /* 纵向滚动条*/
        height: 3px;
        /* 横向滚动条 */
        background-color: #fff;
    }

    /*定义滚动条轨道 内阴影*/
    ::-webkit-scrollbar-track {
        box-shadow: inset 0 0 6px rgba(0, 0, 0, 0);
        background-color: #fff;
    }

    /*定义滑块 内阴影*/
    ::-webkit-scrollbar-thumb {
        box-shadow: inset 0 0 6px rgba(0, 0, 0, 0);
        background-color: #bbb;
        border-radius: 10px;
    }

    .disabled-opacity: {
        /* pointer-events: none; */
        opacity: 0.4;
    }
</style>
