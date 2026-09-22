define({
  name: "basic",
  template: `
<div id="basicGoods">
  <div class="vue-main-title">
    <div class="vue-main-title-left"></div>
    <div class="vue-main-title-content">{{lang.goods.info}}</div>
  </div>
  <el-form :rules="rules" :model="form" ref="rform" label-position="left" label-width="110px">
    <div class="h-baseinfo-content" style="margin-left: 15px;background-color: #F8F9FB;padding:20px;">
      <el-row>
        <el-col :span="8">
          <!-- 商品名称 -->
          <el-form-item :label="lang.goods.title" prop="title" required>
            <el-input v-model="form.goods.title" ref="title" maxlength="50" show-word-limit style="width: 83%" v-if="!nationList.length" :placeholder="lang.goods.title_tips" />
            <div v-for="(item,index) in nationList" :key="'title_' + index" style="display: flex;margin-bottom: 10px;" v-if="nationList.length">
              <span :style="{background: item.background,color: item.color,'white-space':'nowrap',height:'inherit',padding: '0 12px','border-radius': '2px'}"  v-if="item.name">{{item.name}}</span>
              <el-input v-model="form.goods.lang[item.value].title"></el-input>
            </div>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <!-- 商品分类 -->
          <el-form-item :label="lang.goods.category" required prop="category"  v-if="whetherHide('category')">
            <el-row v-for="(categoryItem,itemIndex) in categoryList" :key="'categoryList_' + itemIndex">
              <el-col :span="isLevel2 ? 6:5" v-if="form.category_level * 1 >= 1">
                <el-select :placeholder="lang.goods.please_select_a_first_level_category" v-model="categoryItem[0].id" ref="category"  clearable filterable @change="onChangeFirst($event,itemIndex,categoryItem[0].id)" >
                  <el-option :value="firstItem.id" :label="firstItem.name" v-for="(firstItem,firstIndex) in form.category_list" :key="'category_list1_' + firstItem.id"></el-option>
                </el-select>
              </el-col>
              <el-col :span="isLevel2 ? 18:5" v-if="form.category_level * 1 >= 2">
                <el-select v-if="secondChildrensIsshow" :placeholder="lang.goods.please_select_a_secondary_category" :style="{ width: isLevel2 ? '83%' : '90%' }" style="margin-left: 10px;" v-model="categoryItem[1].id" ref="category" :multiple="isLevel2" :collapse-tags="isLevel2" clearable  filterable @change="onChangeSecond($event,itemIndex,categoryItem[1].id)"  @focus="onFocusSecond(itemIndex,categoryItem[0])">
                  <el-option :value="secondItem.id" :label="secondItem.name" v-for="(secondItem,secondIndex) in category_list_box[itemIndex].secondCategory" :key="'category_list2_' + secondItem.id"></el-option>
                </el-select>
              </el-col>
              <el-col :span="14" v-if="form.category_level * 1 >= 3">
                <el-select v-if="threeChildrensIsshow" :placeholder="lang.goods.please_select_a_tertiary_category" style="margin-left: 10px;width: 78%;" v-model="categoryItem[2].id" ref="category" :multiple="isLevel3" :collapse-tags="isLevel3" clearable  filterable @change="onChangeThree($event,itemIndex)" @focus="onFocusThree(itemIndex,categoryItem[1],categoryItem[0])">
                  <el-option :value="threeItem.id" :label="threeItem.name" v-for="(threeItem,threeIndex) in category_list_box[itemIndex].threeCategory" :key="'category_list3_' + threeItem.id"></el-option>
                </el-select>
              </el-col>
              <el-col :span="6" v-if="form.category_to_option_open==1&&form.goods.category_to_option.length!==0">
                <el-select :placeholder="lang.goods.select_specification" style="margin-left: 10px;" v-model="form.goods.category_to_option[itemIndex].goods_option_id" ref="category" clearable  filterable>
                  <el-option :value="optionItem.id" :label="optionItem.title" v-for="(optionItem,optionIndex) in form.options" :key="'category_list4_' + optionItem.id"></el-option>
                </el-select>
              </el-col>
            </el-row>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <!-- 工艺材质 -->
          <el-form-item label="工艺/材质" required>
            <el-select v-model="form.goods.craft_materials" ref="craft_materials" placeholder="请选择工艺/材质" multiple collapse-tags clearable filterable style="width: 90%">
              <el-option
                v-for="item in craftMaterialsList"
                :key="'craftMaterialsList_' + item.id"
                :label="item.name"
                :value="item.id">
              </el-option>
            </el-select>
          </el-form-item>

          <!-- 商品品牌 -->
          <!-- <el-form-item :label="lang.goods.brand" v-if="whetherHide('brand')"> -->
            <!-- <el-select v-model="form.goods.brand_id" :placeholder="lang.goods.brand_tips" clearable filterable style="width: 80%"> -->
              <!-- <el-option -->
                <!-- v-for="item in form.brand" -->
                <!-- :key="item.id" -->
                <!-- :label="item.name" -->
                <!-- :value="item.id"> -->
              <!-- </el-option> -->
            <!-- </el-select> -->
          <!-- </el-form-item> -->
        </el-col>
      </el-row>

      <el-row>
        <el-col :span="8">
          <!-- 标签关键词 -->
          <el-form-item label="标签/关键词">
            <el-input v-model="form.goods.keywords" style="width: 83%" placeholder="如: 班台办公桌大班台实木班台油漆班台" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <!-- 商品风格 -->
          <el-form-item label="商品风格" required>
            <el-select v-model="form.goods.goods_style" ref="goods_style" placeholder="请选择商品风格" multiple collapse-tags clearable filterable style="width: 90%">
              <el-option
                v-for="item in goodsStyleList"
                :key="'goodsStyleList_' + item.id"
                :label="item.name"
                :value="item.id">
              </el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <!-- 搭配组合 -->
          <el-form-item label="搭配组合">
            <!-- 选择关联商品 -->
            <el-badge :value="form.goods?.goods_relation?.length" :hidden="form.goods?.goods_relation?.length==0" type="primary" style="width: 90%;">
              <el-button @click="choseRelationGoods" style="width: 100%;">{{lang.goods.choose_relation_goods}}</el-button>
            </el-badge>
          </el-form-item>
        </el-col>
      </el-row>

      <el-row>
        <el-col :span="24">
          <el-row>
            <el-col :span="8">
              <!-- 商品单位 -->
              <el-form-item :label="lang.goods.sku" required v-if="whetherHide('sku')">
                <el-input v-model="form.goods.sku" ref="sku" :placeholder="lang.goods.sku_tips" style="width: 83%"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <!-- 质保期 -->
              <el-form-item label="质保期" required>
                <el-input v-model="form.goods.warranty" ref="warranty" oninput="if(value<0)value=0" type="number" :placeholder="lang.goods.warranty_tips" style="width: 90%"></el-input>
              </el-form-item>
            </el-col>

            <!-- <el-col :span="8">
              <el-form-item label="商品属性">
                <el-checkbox v-model="form.goods.is_discount" :true-label="1" :false-label="0">特价</el-checkbox>
                <el-checkbox v-model="form.goods.is_hot" :true-label="1" :false-label="0" style="margin-top:15px;">精选</el-checkbox>
              </el-form-item>
            </el-col> -->
          </el-row>

          <el-row>
            <el-col :span="8">
              <!-- 库存类型 产能、库存 -->
              <el-form-item :label="lang.goods.is_stock" required>
                <el-radio-group v-model="form.goods.is_stock" style="width:100%;">
                  <el-radio :label="1">{{lang.goods.goods_capacity}}</el-radio>
                  <el-input style="width:35%;margin-left: -15px;margin-right: 20px;" v-if="form.goods.is_stock==1" v-model="form.goods.stock" ref="stock" oninput="if(value<0)value=0" type="number" :placeholder="lang.goods.goods_capacity_tips">
                    </el-input>
                  <el-radio :label="2">{{lang.goods.spot_stock}}</el-radio>
                  <el-input style="width:35%;margin-left: -15px;" v-if="form.goods.is_stock==2" v-model="form.goods.stock" ref="stock" oninput="if(value<0)value=0" type="number" :placeholder="lang.goods.spot_stock_tips">
                    </el-input>
                </el-radio-group>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <!-- 上传商品图册 -->
              <el-form-item :label="lang.goods.e_catalog_pdf" required>
              
                <!--
                <div style="display:flex;justify-content:space-between;align-items:center;border:1px solid #DCDFE6;width:90%;">
                  <div style="display:flex;width:calc(100% - 126px);padding-left:20px;color:#C0C4CC;background-color:#fff;
                    flex-direction:row;flex-wrap:wrap;justify-content:space-between;align-items:center;">
                    <div :style="{ maxWidth: eCatalogPdfName ? 'calc(100% - 26px)' : '100%' }" class="h-text-overflow">
                      <el-tooltip v-if="eCatalogPdfName && eCatalogPdfName.length > 12" :content="eCatalogPdfNameShow" placement="bottom">
                        <span>{{ eCatalogPdfNameShow }}</span>
                      </el-tooltip>
                      <span v-else>{{ eCatalogPdfNameShow }}</span>
                    </div>
                    <div v-show="eCatalogPdfName" class="el-icon-close h-icon-close" @click="clearPdf"></div>
                  </div>
              
                  <div style="position: relative;">
                    <el-button type="text" 
                      style="padding: 0 20px;line-height:40px;" 
                      @click="showSelectMaterialPopup = !showSelectMaterialPopup;materialType = '11';upload_type='pdf';formFieldName='pdf';">
                      {{ uploadBtnText(eCatalogPdfName) }}
                    </el-button>
                    <el-tooltip content="点击修改PDF页码" placement="top">
                      <span v-if="form.goods.pdf_page.length > 0" @click="editPageCount" class="h-badge">
                        {{ form.goods.pdf_page.length }}
                      </span>
                    </el-tooltip>
                  </div>
                </div>
                -->

                 <div style="display:flex;justify-content:space-between;align-items:center;border:1px solid #DCDFE6;width:126px">
                  <div style="position: relative;" ref="goods_atlas" tabindex="0">
                    <el-button type="text" 
                        style="padding: 0 20px;line-height:40px;"
                        @click.stop="openImagePreviewUpload(form.goods.atlas,'atlas','商品图册','atlas')">
                        {{ uploadBtnText(eCatalogPdfName) }}
                      </el-button>

                       <span v-if="form.goods.atlas?.data?.length > 0" class="h-badge">
                        {{ form.goods.atlas.data.length }}
                      </span>
                    </div>
                  </div>
              </el-form-item>
            </el-col>
          </el-row>

          <el-row>
            <el-col :span="8">
              <!-- 减库存/产能方式 -->
              <el-form-item :label="form.goods.is_stock==1?lang.goods.reduce_capacit_method:lang.goods.reduce_stock_method" required>
                <el-radio-group v-model="form.goods.reduce_stock_method">
                  <el-tooltip :content="form.goods.is_stock==1?lang.goods.capacit_tips_1:lang.goods.stock_tips_1" placement="bottom">
                    <el-radio :label="0">{{form.goods.is_stock==1?lang.goods.capacit_create_reduce:lang.goods.stock_create_reduce}}</el-radio>
                  </el-tooltip>
                  <el-tooltip :content="form.goods.is_stock==1?lang.goods.capacit_tips_2:lang.goods.stock_tips_2" placement="bottom">
                    <el-radio :label="1">{{form.goods.is_stock==1?lang.goods.capacit_pay_reduce:lang.goods.stock_pay_reduce}}</el-radio>
                  </el-tooltip>
                  <el-tooltip :content="form.goods.is_stock==1?lang.goods.capacit_tips_3:lang.goods.stock_tips_3" placement="bottom">
                    <el-radio :label="2">{{form.goods.is_stock==1?lang.goods.capacit_not_reduce:lang.goods.stock_not_reduce}}</el-radio>
                  </el-tooltip>
                </el-radio-group>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <!-- 保养手册 -->
              <el-form-item label="保养手册">
              <!--
                <div style="display:flex;justify-content:space-between;align-items:center;border:1px solid #DCDFE6;width:90%;">
                  <div style="display:flex;width:calc(100% - 126px);padding-left:20px;color:#C0C4CC;background-color:#fff;
                    flex-direction:row;flex-wrap:wrap;justify-content:space-between;align-items:center;">
                    <div :style="{ maxWidth: maintenanceDocName ? 'calc(100% - 26px)' : '100%' }" class="h-text-overflow">
                      <el-tooltip v-if="maintenanceDocName && maintenanceDocName.length > 12" :content="maintenanceDocNameShow" placement="bottom">
                        <span>{{ maintenanceDocNameShow }}</span>
                      </el-tooltip>
                      <span v-else>{{ maintenanceDocNameShow }}</span>
                    </div>
                    <div v-show="maintenanceDocName" class="el-icon-close h-icon-close" @click="clearMaintenanceDoc"></div>
                  </div>
                  <el-button type="text" style="padding: 0 20px;line-height:40px;" @click="showSelectMaterialPopup = !showSelectMaterialPopup;materialType = '11';upload_type='pdf';formFieldName='maintenance_doc';">
                    {{ uploadBtnText(maintenanceDocName) }}
                  </el-button>
                </div>
              -->

                <div style="display:flex;justify-content:space-between;align-items:center;border:1px solid #DCDFE6;width:126px">
                  <div style="position: relative;">
                    <el-button type="text" 
                        style="padding: 0 20px;line-height:40px;"
                        @click.stop="openImagePreviewUpload(form.goods.maintenance_doc,'maintenance_doc','保养手册','maintenance_doc')">
                        {{ uploadBtnText(eCatalogPdfName) }}
                      </el-button>

                       <span v-if="form.goods.maintenance_doc?.data?.length > 0" class="h-badge">
                        {{ form.goods.maintenance_doc.data.length }}
                      </span>
                    </div>
                  </div>
              </el-form-item>
            </el-col>
          </el-row>

          <el-row>
            <el-col :span="8">
              <!-- 生产货期 -->
              <el-form-item v-if="form.goods.is_stock==1" label="生产货期" required class="h-form-content">
                <el-input v-model="form.goods.lead_time" ref="lead_time" oninput="if(value<0)value=0" type="number" placeholder="生产货期/天" style="width: 83%"></el-input>
                <!-- 是否能退货 -->
                <!-- <el-form-item :label="lang.goods.no_refund" v-if="whetherHide('no_refund')" label-width="90px" style="margin-left:20px;">
                  <el-radio-group v-model="form.goods.no_refund">
                    <el-radio :label="1">{{common_lang.yes}}</el-radio>
                    <el-radio :label="0">{{common_lang.no}}</el-radio>
                  </el-radio-group>
                </el-form-item> -->
              </el-form-item>
              <!-- 是否能退货 -->
              <!-- <el-form-item v-if="form.goods.is_stock==2 && whetherHide('no_refund')" :label="lang.goods.no_refund">
                <el-radio-group v-model="form.goods.no_refund">
                  <el-radio :label="1">{{common_lang.yes}}</el-radio>
                  <el-radio :label="0">{{common_lang.no}}</el-radio>
                </el-radio-group>
              </el-form-item> -->
              <!-- 占位 -->
              <div v-if="form.goods.is_stock==2" style="height:1px"></div>
            </el-col>
            <el-col :span="8">
              <!-- 安装指南 -->
              <!-- <el-form-item label="安装指南">
              
                <div style="display:flex;justify-content:space-between;align-items:center;border:1px solid #DCDFE6;width:90%;">
                  <div style="display:flex;width:calc(100% - 126px);padding-left:20px;color:#C0C4CC;background-color:#fff;
                    flex-direction:row;flex-wrap:wrap;justify-content:space-between;align-items:center;">
                    <div :style="{ maxWidth: installGuideName ? 'calc(100% - 26px)' : '100%' }" class="h-text-overflow">
                      <el-tooltip v-if="installGuideName && installGuideName.length > 12" :content="installGuideNameShow" placement="bottom">
                        <span>{{ installGuideNameShow }}</span>
                      </el-tooltip>  
                      <span v-else>{{ installGuideNameShow }}</span>
                    </div>
                    <div v-show="installGuideName" class="el-icon-close h-icon-close" @click="clearInstallGuide"></div>
                  </div>
                  <el-button type="text" style="padding: 0 20px;line-height:40px;" @click="showSelectMaterialPopup = !showSelectMaterialPopup;materialType = '11';upload_type='pdf';formFieldName='install_guide';">
                    {{ uploadBtnText(installGuideName) }}
                  </el-button>
                </div>

                
                <div style="display:flex;justify-content:space-between;align-items:center;border:1px solid #DCDFE6;width:126px">
                  <div style="position: relative;">
                    <el-button type="text" 
                      style="padding: 0 20px;line-height:40px;"
                      @click.stop="openImagePreviewUpload(form.goods.install_guide,'install_guide','安装指南','install_guide')">
                      {{ uploadBtnText(eCatalogPdfName) }}
                    </el-button>

                    <span v-if="form.goods.install_guide?.data?.length > 0" class="h-badge">
                      {{ form.goods.install_guide.data.length }}
                    </span>
                  </div>
                </div>
              </el-form-item> -->
            </el-col>
          </el-row>
        </el-col>
            

        <!-- <el-col :span="8"> -->
          <!-- 材质说明 -->
          <!-- <el-form-item label="材质说明" required> -->
          <!--   <el-input v-model="form.goods.structure" ref="structure" :rows="11" type="textarea" style="width: 90%;" placeholder="请输入商品结构、配件及材质说明" /> -->
          <!-- </el-form-item> -->
        <!-- </el-col> -->
      </el-row>

      <el-row>
        <el-col :span="12">
          <!-- 上传商品图片 -->
          <el-form-item :label="lang.goods.thumb" prop="main_url">
            <div style="display:flex;flex-direction:row;flex-wrap:wrap;">
              <!-- 上传商品主图 -->
              <!--
              <div class="upload-img-box">
                  <div class="upload-img-boxed" @click="openImageInput(0,'thumb')">
                    <img :src="form.goods.thumb_link" v-show="form.goods.thumb_link" />
                    <div class="icon-boxed mt-15" v-show="!form.goods.thumb">
                      <div class="el-icon-plus"></div>
                    </div>
                    <div class="upload-img-tips" v-show="!form.goods.thumb">建议尺寸: 640 * 640</br>或正方型图片</div>
                    <div class="upload-btn">{{!form.goods.thumb ? "点击上传" : "点击修改"}}</div>
                  </div>
                <div class="upload-img-title"><span>*</span> {{lang.goods.thumb_tips_1}}</div>
              </div>
              -->

              <!--
              <div class="upload-img-box">
                <div class="upload-img-boxed" @click="openImageInput(1,'thumb')">
                  <img :src="form.goods.main_url[0]?.thumb_link" @click.stop="openImagePreview(form.goods.main_url,'main','商品主图','thumb')" v-if="hasMainUrl" />
                  <div class="icon-boxed mt-15" v-show="!hasMainUrl">
                    <div class="el-icon-plus"></div>
                  </div>
                  <div class="upload-img-tips" v-show="!hasMainUrl">建议尺寸: 640 * 640</br>或正方型图片</div>
                  <div class="upload-btn">
                    {{!hasMainUrl ? "点击上传" : "已上传"+(form.goods.main_url.length||0)+"张图片"}}
                  </div>
                </div>
                <div class="upload-img-title">商品主图</div>
              </div>
              -->

              <!-- <div class="upload-img-box" ref="main_url" tabindex="0">
                <div class="upload-img-boxed" @click="openImagePreview(form.goods.main_url,'main','商品主图','thumb')">
                  <img :src="form.goods.main_url?.[0]?.thumb_link" v-if="hasMainUrl"/>
                  <div class="icon-boxed mt-15" v-show="!hasMainUrl">
                    <div class="el-icon-plus"></div>
                  </div>
                  <div class="upload-img-tips" v-show="!hasMainUrl">建议尺寸: 640 * 640</br>或正方型图片</div>
                  <div class="upload-btn">
                    {{!hasMainUrl ? "点击上传" : "已上传"+(form.goods.main_url.length||0)+"张图片"}}
                  </div>
                </div>
                <div class="upload-img-title">商品主图</div>
              </div> -->
              
              <!-- 商品效果图 -->
              <!--
              <div class="upload-img-box">
                <div class="upload-img-boxed" @click="openImageInput(1,'thumb_url')">
                  <img :src="form.goods.thumb_url[0].thumb_link" @click.stop="openImagePreview(form.goods.thumb_url,'other','商品效果图','thumb_url')" v-if="hasThumbUrl" />
                  <div class="icon-boxed mt-15" v-show="!hasThumbUrl">
                    <div class="el-icon-plus"></div>
                  </div>
                  <div class="upload-img-tips" v-show="!hasThumbUrl">建议尺寸: 640 * 640</br>或正方型图片</div>
                  <div class="upload-btn">
                    {{!hasThumbUrl ? "点击上传" : "已上传"+form.goods.thumb_url.length+"张图片"}}
                  </div>
                </div>
                <div class="upload-img-title">{{lang.goods.thumb_tips_2}}</div>
              </div>
              -->

               <div class="upload-img-box">
                <div class="upload-img-boxed" @click="openImagePreview(form.goods.thumb_url,'other','商品效果图','thumb_url')">
                  <img :src="form.goods.thumb_url[0].thumb_link" v-if="hasThumbUrl" />
                  <div class="icon-boxed mt-15" v-show="!hasThumbUrl">
                    <div class="el-icon-plus"></div>
                  </div>
                  <div class="upload-img-tips" v-show="!hasThumbUrl">建议尺寸: 640 * 640</br>或正方型图片</div>
                  <div class="upload-btn">
                    {{!hasThumbUrl ? "点击上传" : "已上传"+form.goods.thumb_url.length+"张图片"}}
                  </div>
                </div>
                <div class="upload-img-title">{{lang.goods.thumb_tips_2}}</div>
              </div>

              <!-- 商品实拍图 -->
              <!--
              <div class="upload-img-box">
                <div class="upload-img-boxed" @click="openImageInput(1,'real_image')">
                  <img :src="form.goods.real_image[0].thumb_link" @click.stop="openImagePreview(form.goods.real_image,'real_image','商品实拍图','real_image')" v-if="hasRealImage" />
                  <div class="icon-boxed mt-15" v-show="!hasRealImage">
                    <div class="el-icon-plus"></div>
                  </div>
                  <div class="upload-img-tips" v-show="!hasRealImage">建议尺寸: 640 * 640</br>或正方型图片</div>
                  <div class="upload-btn">
                    {{!hasRealImage ? "点击上传" : "已上传"+form.goods.real_image.length+"张图片"}}
                  </div>
                </div>
                <div class="upload-img-title">商品实拍图</div>
              </div>
              -->

                <div class="upload-img-box">
                <div class="upload-img-boxed" @click="openImagePreview(form.goods.real_image,'real_image','商品实拍图','real_image')">
                  <img :src="form.goods.real_image[0].thumb_link" v-if="hasRealImage" />
                  <div class="icon-boxed mt-15" v-show="!hasRealImage">
                    <div class="el-icon-plus"></div>
                  </div>
                  <div class="upload-img-tips" v-show="!hasRealImage">建议尺寸: 640 * 640</br>或正方型图片</div>
                  <div class="upload-btn">
                    {{!hasRealImage ? "点击上传" : "已上传"+form.goods.real_image.length+"张图片"}}
                  </div>
                </div>
                <div class="upload-img-title">商品实拍图</div>
              </div>
              
              <!-- 走线示意图 -->
              <!--
              <div class="upload-img-box">
                <div class="upload-img-boxed" @click="openImageInput(1,'wiring_diagram')">
                  <img :src="form.goods.wiring_diagram[0].thumb_link" @click.stop="openImagePreview(form.goods.wiring_diagram,'wiring_diagram','走线示意图','wiring_diagram')" v-if="hasWiringDiagram" />
                  <div class="icon-boxed mt-15" v-show="!hasWiringDiagram">
                    <div class="el-icon-plus"></div>
                  </div>
                  <div class="upload-img-tips" v-show="!hasWiringDiagram">建议尺寸: 640 * 640</br>或正方型图片</div>
                  <div class="upload-btn">
                    {{!hasWiringDiagram ? "点击上传" : "已上传"+form.goods.wiring_diagram.length+"张图片"}}
                  </div>
                </div>
                <div class="upload-input-title">
                  <el-input v-model="form.goods.other_name" ref="other_name" placeholder="请输入图片类型" size="small" style="width: 140px;" />
                </div>
              </div>
              -->
               <div class="upload-img-box">
                <div class="upload-img-boxed" @click="openImagePreview(form.goods.wiring_diagram,'wiring_diagram','其它','wiring_diagram')">
                  <img :src="form.goods.wiring_diagram[0].thumb_link" v-if="hasWiringDiagram" />
                  <div class="icon-boxed mt-15" v-show="!hasWiringDiagram">
                    <div class="el-icon-plus"></div>
                  </div>
                  <div class="upload-img-tips" v-show="!hasWiringDiagram">建议尺寸: 640 * 640</br>或正方型图片</div>
                  <div class="upload-btn">
                    {{!hasWiringDiagram ? "点击上传" : "已上传"+form.goods.wiring_diagram.length+"张图片"}}
                  </div>
                </div>
                <div class="upload-input-title">
                  <el-input v-model="form.goods.other_name" ref="other_name" placeholder="请输入图片类型" size="small" style="width: 140px;" />
                </div>
              </div>

            </div>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="商品视频">
            <!-- 商品视频 -->
            <div class="upload-img-box">
              <div class="upload-img-boxed" @click="displaySelectMaterialPopup('video',3)">
                <video :src="form.goods.goods_video_link" v-if="form.goods.goods_video_link" controls="controls" class="video-view"></video>
                <div class="icon-boxed mt-30" v-show="!form.goods.goods_video_link">
                  <div class="el-icon-plus"></div>
                </div>
                <div class="upload-btn">{{!form.goods.goods_video_link ? "点击上传视频" : "点击修改视频"}}</div>
                <i v-if="form.goods.goods_video_link" class="el-icon-close" @click.stop="removeVideo('video')"></i>
              </div>
              <div class="upload-img-title2">设置后商品详情首图默认显示视频，建议时长9-30秒</div>
            </div>
          </el-form-item>
        </el-col>
      </el-row>
    </div>

    <!-- 商品规格 -->
    <div class="vue-main-title">
      <div class="vue-main-title-left"></div>
      <div class="vue-main-title-content">{{lang.goods.title_spec}}</div>
      <!-- 新增规格项按钮 -->
      <!-- <div class="el-icon-plus" style="color:#29BA9C;align-content:center;font-weight: bold;"></div> -->
      <!-- <el-button @click="addSpecification" type="text">{{lang.goods.spec_title_add}}</el-button> -->
      <!-- 复制设置按钮 -->
      <el-button @click="openCopySettings" type="text" style="margin-right: 10px;">复制设置</el-button>
      <!-- 批量设置按钮 -->
      <el-button @click="openBatchSetDialog" type="text" style="margin-right: 30px;">{{lang.goods.batch_set}}</el-button>
    </div>
    
    <div style="margin-left: 15px;padding:20px 0 20px 20px;">
      <!-- 循环规格组 -->
      <div v-for="(specItem,specItemIndex) in goodsSpecs" :key="'specItem_' + specItemIndex" >
        <!-- 商品规格组名称 -->
        <el-form-item class="h-form-spec-title" :label="lang.goods.spec_title">
          <el-input v-model="specItem.title" @blur="checkSpecification(specItem,specItemIndex)" ></el-input>
          <div class="el-icon-circle-close h-icon-circle-close" v-if="goodsSpecs.length > 1" @click="confirmRemoveSpecification(specItem,specItemIndex)"></div>
        </el-form-item>
        <!-- 商品规格值 -->
        <el-form-item :label="lang.goods.spec_value" label-width="100px">
          <div class="specvalue-area">
            <spec-value-row
              :list="specItem.spec_item"
              :depth="1"
              :spec-index="specItemIndex"
              :add-disabled="isAddSpecValueDisabled(specItemIndex)"
              :selected-top-index="selectedTopIndex"
              :selected-path-ids="selectedPathIds"
              @blur="checkAndGenerate"
              @add-child="addChildSpecValue"
              @remove="removeSpecificationValue"
              @add-sibling-to-list="addSiblingSpecValue"
              @on-select="handleSpecValueSelect"
            />
          </div>
        </el-form-item>
        <div v-show="specValueIndex == selectedTopIndex" v-for="(specValue,specValueIndex) in specItem.spec_item" :key="'specValue_' + specItemIndex + '_' + specValueIndex" >
          <!-- 模型类型 -->
          <el-form-item label="模型类型">
            <el-radio-group v-model="specValue.productType" @change="onChangeProductType(specValue)" style="margin-top:7px;">

              <!-- <el-radio :label="1">常规商品
                <el-tooltip effect="dark" content="适用商品：标准成品家具，例如：班台、座椅、会议桌、文件柜等" placement="top" :open-delay="200">
                  <i class="el-icon-question"></i>
                </el-tooltip>
              </el-radio> -->

              <el-radio :label="5">常规商品
                <el-tooltip effect="dark" content="适用商品：标准成品家具、不同单元可任意自由搭配组合的商品" placement="top" :open-delay="200">
                  <i class="el-icon-question"></i>
                </el-tooltip>
              </el-radio>

              <el-radio :label="2">主辅拼接商品
                <el-tooltip effect="dark" content="适用商品：主体单元为主导（A），辅助单元（B）可以重复拼接的模块化商品，拼接模式：A+B+B+B..." placement="top" :open-delay="200">
                  <i class="el-icon-question"></i>
                </el-tooltip>
              </el-radio>

              <el-radio :label="3">多元拼接商品
                <el-tooltip effect="dark" content="适用商品：拼接之后支撑结构会发生变化的模块化商品，拼接模式：A+B+B...+C" placement="top" :open-delay="200">
                  <i class="el-icon-question"></i>
                </el-tooltip>
              </el-radio>

              <el-radio :label="4">屏风卡位
                <el-tooltip effect="dark" content="适用商品：由屏风与工作台组成的半封闭式模块化办公桌" placement="top" :open-delay="200">
                  <i class="el-icon-question"></i>
                </el-tooltip>
              </el-radio>
              
            </el-radio-group>
          </el-form-item>
        </div>
      </div>

      <!-- 循环遍历，商品规格详细信息 -->
      <div v-for="(specDetail, index) in specDetails" :key="'specDetail_' + index">
        <!-- Tabs 组, 一个规格值组合有多个模型 -->
        <el-tabs v-show="specDetailSelectedIndex == index" v-model="specDetail.activeTab">
          <el-tab-pane v-for="(detail, tabIndex) in specDetail.modelTypes" :key="'detail_' + index + '_' + detail.modelType" :label="detail.title" :name="detail.modelType.toString()">
            <!-- <div class="h-spec-title">{{detail.option.combination}}</div> -->
            <div class="h-spec-box">
              <el-row>
                <el-col :span="14">
                  <el-row :gutter="30">
                    <el-col :span="9">
                      <!-- 商品单价 -->
                      <el-form-item :label="lang.goods.spec_product_price" required label-width="100px">
                        <el-input v-model="detail.option.product_price" :ref="'product_price_' + index + '_' + tabIndex" :placeholder="lang.goods.spec_product_price_tips" :min="0" type="number" oninput="if(value<0)value=0" />
                      </el-form-item>
                    </el-col>
                    <el-col :span="9">
                      <!-- 商品型号 -->
                      <el-form-item label="商品型号" required label-width="100px">
                        <el-input v-model="detail.option.product_model" :ref="'product_model_' + index + '_' + tabIndex" placeholder="请输入商品型号" />
                      </el-form-item>
                    </el-col>
                    <el-col :span="6">
                      <!-- 人数位 -->
                      <el-form-item v-if="specDetail.productType==2 || specDetail.productType==3" label="人数位" required label-width="90px">
                        <el-select v-model="detail.option.singleType" :ref="'singleType_' + index + '_' + tabIndex" @change="updateSingleType(index, tabIndex, detail.option.singleType)" :disabled="[1,2,3].includes(detail.modelType)" placeholder="选择人数位" >
                          <el-option v-for="item in singleTypeOptions" 
                            :key="'singleType_' + index + '_' + tabIndex + '_' + item.value" 
                            :label="item.label" 
                            :value="item.value">
                          </el-option>
                        </el-select>
                      </el-form-item>
                    </el-col>
                  </el-row>

                  <el-row :gutter="30">
                    <el-col :span="9">
                      <!-- 上传2D模型 -->
                      <el-form-item :label="lang.goods.upload_2d_model" required label-width="101px">
                        <div style="display:flex;align-items:center;border:1px solid #DCDFE6;margin-left: -1px;" :ref="'cad_plan_model_' + index + '_' + tabIndex" tabindex="0">
                          <div style="display:flex;width:calc(100% - 101px);padding-left:9px;color:#C0C4CC;background-color:#fff;
                            flex-direction:row;flex-wrap:wrap;justify-content:space-between;align-items:center;">
                            <div :style="{ maxWidth: detail.option.cad_plan_modelName ? 'calc(100% - 26px)' : '100%' }" class="h-text-overflow">
                              <el-tooltip v-if="detail.option.cad_plan_modelName && detail.option.cad_plan_modelName.length > 4" :content="detail.option.cad_plan_modelName" placement="bottom">
                                <span>{{ detail.option.cad_plan_modelName?detail.option.cad_plan_modelName:lang.goods.upload_file_tips3 }}</span>
                              </el-tooltip>
                              <span v-else>{{ detail.option.cad_plan_modelName?detail.option.cad_plan_modelName:lang.goods.upload_file_tips3 }}</span>
                             </div>
                            <div v-show="detail.option.cad_plan_modelName" class="el-icon-close h-icon-close" @click="clearCADModel(detail.option)"></div>
                          </div>
                          <el-button type="text" style="padding: 0 5px 0 10px;line-height:40px;" @click="openImageInput3(index,tabIndex,'cad_plan_model','9')">{{detail.option.cad_plan_modelName.length>0?lang.goods.upload_file_text1:lang.goods.upload_file_text}}</el-button>
                        </div>
                      </el-form-item>
                    </el-col>
                    <el-col :span="9">
                      <!-- 上传图片 -->
                      <el-form-item label="上传白底图" required label-width="100px">
                        <div style="display:flex;align-items:center;border:1px solid #DCDFE6;" :ref="'option_thumb_' + index + '_' + tabIndex" tabindex="0">
                          <div style="display:flex;width:calc(100% - 101px);padding-left:10px;color:#C0C4CC;background-color:#fff;
                            flex-direction:row;flex-wrap:wrap;justify-content:space-between;align-items:center;">
                            <div :style="{ maxWidth: detail.option.thumbName ? 'calc(100% - 26px)' : '100%' }" class="h-text-overflow">
                              <el-popover v-if="detail.option.thumbName" width="300" trigger="hover" placement="top">
                                <div class="h-popover">
                                  <el-image :src="detail.option.thumb" fit="contain" />
                                  <div>{{detail.option.thumbName}}</div>
                                </div>
                                <div slot="reference">
                                  <span>{{ detail.option.thumbName?detail.option.thumbName:lang.goods.upload_img_tips2 }}</span>
                                </div>
                              </el-popover>
                              <span v-else>{{ detail.option.thumbName?detail.option.thumbName:lang.goods.upload_img_tips2 }}</span>
                             </div>
                            <div v-show="detail.option.thumbName" class="el-icon-close h-icon-close" @click="clearThumb(detail.option)"></div>
                          </div>
                          <el-button type="text" style="padding: 0 5px 0 10px;line-height:40px;" @click="openImageInput2(index,tabIndex,'option_thumb','1')">{{detail.option.thumbName.length>0?lang.goods.upload_file_text1:lang.goods.upload_file_text}}</el-button>
                        </div>
                      </el-form-item>
                    </el-col>
                    <el-col :span="6">
                      <!-- 包装件数 -->
                      <el-form-item :label="lang.goods.package_number" required label-width="90px">
                        <el-input v-model="detail.option.package_number" :ref="'package_number_' + index + '_' + tabIndex"  @blur="updatePackageNumber(index, tabIndex, detail.option.package_number)" :placeholder="lang.goods.package_number_tips" />
                      </el-form-item>
                    </el-col>
                  </el-row>

                  <el-row :gutter="30">
                    <el-col :span="9"></el-col>
                    <el-col :span="9"></el-col>
                    <el-col :span="6">
                      <!-- 商品重量（KG） -->
                      <!-- <el-form-item :label="lang.goods.goods_weight" label-width="130px" required> -->
                        <!-- <el-input v-model="detail.option.weight" :ref="'weight_' + index + '_' + tabIndex" style="width: 90%;" :placeholder="lang.goods.goods_weight_tips" :min="0" type="number" oninput="if(value<0)value=0" /> -->
                      <!-- </el-form-item> -->
                    </el-col>
                  </el-row>

                  <el-row :gutter="30">
                    <el-col :span="9">
                      <!-- 上传3Dmax模型 -->
                      <el-form-item :label="lang.goods.upload_3d_max" required label-width="100px">
                        <div style="display:flex;align-items:center;border:1px solid #DCDFE6;" :ref="'option_d3MaxUrl_' + index + '_' + tabIndex" tabindex="0">
                          <div style="display:flex;width:calc(100% - 101px);padding-left:20px;color:#C0C4CC;background-color:#fff;
                            flex-direction:row;flex-wrap:wrap;justify-content:space-between;align-items:center;">
                            <div :style="{ maxWidth: detail.option.d3MaxName ? 'calc(100% - 26px)' : '100%' }" class="h-text-overflow">
                              <el-tooltip v-if="detail.option.d3MaxName && detail.option.d3MaxName.length > 4" :content="detail.option.d3MaxName" placement="bottom">
                                <span>{{ detail.option.d3MaxName?detail.option.d3MaxName:lang.goods.upload_file_tips2 }}</span>
                              </el-tooltip>
                              <span v-else>{{ detail.option.d3MaxName?detail.option.d3MaxName:lang.goods.upload_file_tips2 }}</span>
                             </div>
                            <div v-show="detail.option.d3MaxName" class="el-icon-close h-icon-close" @click="clear3DMax(detail.option)"></div>
                          </div>
                          <el-button type="text" style="padding: 0 5px 0 10px;line-height:40px;" @click="openImageInput3(index,tabIndex,'d3model','10')">{{detail.option.d3MaxName?.length>0?lang.goods.upload_file_text1:lang.goods.upload_file_text}}</el-button>
                        </div>
                      </el-form-item>
                    </el-col>

                    <el-col :span="9">
                      <el-button style="width: 100%;position:relative;" @click="showThree(index,tabIndex,detail)">
                        DIY编辑器
                        <el-tooltip v-show="threeWarning.length" effect="dark" placement="top" :open-delay="300">
                            <i class="el-icon-warning three-warning"></i>
                            <template slot="content">
                              <div style="white-space: normal; line-height: 1.5;">
                                <div v-for="(item,index) of threeWarning" :key="index">{{item}}</div>
                              </div>
                            </template>
                        </el-tooltip>
                      </el-button>
                      <input type="file" :ref="'fileInput_'+detail.option.id"  accept=".glb" @change="glbInputChange" style="display: none;"/>
                    </el-col>

                    <el-col :span="6">
                      <!-- 包装体积 -->
                      <el-form-item label="包装体积" required label-width="90px" >
                        <div style="display: flex;flex-direction: row;">
                          <el-input v-model="detail.option.volume" :ref="'volume_' + index + '_' + tabIndex" placeholder="包装体积/m³" />
                          <span style="margin: 0 10px;">m³</span>
                        </div>
                      </el-form-item>
                    </el-col>
                  </el-row>

                  <!-- 3D部件配色显示 -->
                  <el-row v-if="detail.option.model_param.length>0">
                    <el-col>
                      <el-form-item label="DIY组件" label-width="100px">
                        <el-tooltip content="点击部件部分，可进入3D模型内页编辑部件颜色" placement="bottom">
                          <!-- <div @click="openD3Model(index,tabIndex,detail.option)" class="h-color-display-wrapper"> -->
                          <div @click="showThree(index,tabIndex,detail)" class="h-color-display-wrapper" :ref="'option_d3model_url_' + index + '_' + tabIndex" tabindex="0">
                            <div v-for="(mparam, index) in detail.option.model_param" :key="'modelparam_' + index" class="h-color-module">
                              <div class="h-module-name">{{ mparam.name }}</div>
                              <div class="h-color-list">
                                <div
                                  v-for="color in mergeD3Colors(mparam.default_color, mparam.select_color)"
                                  :key="'color_' + color.id"
                                  class="h-color-circle"
                                  :style="{ backgroundImage: 'url(' + color.thumb + ')' }"
                                ></div>
                              </div>
                            </div>
                          </div>
                        </el-tooltip>
                        <!-- 有接口状态时显示进度条 -->
                        <div class="h-progress" v-if="getOptionModelStatus(detail.option.id)">
                          <div class="h-progress-row">
                            <span class="h-progress-label">
                              {{ getOptionModelStatus(detail.option.id).label }}
                            </span>
                            <el-progress
                              :percentage="getOptionModelStatus(detail.option.id).progress"
                              :status="getOptionModelStatus(detail.option.id).status"
                              style="width: 220px;"
                            />
                          </div>
                        </div>
                      </el-form-item>
                    </el-col>
                  </el-row>

                  <el-row :gutter="30">
                    <el-col :span="9">
                      <!-- 安装指南 -->
                      <el-form-item label="安装指南" label-width="100px">
                        <div style="position: relative;display:flex;justify-content: center;border:1px solid #DCDFE6;">
                          <el-button type="text" style="width: 100%;padding: 0 20px;line-height:40px;"
                            @click.stop="openImagePreviewUpload1(index,tabIndex,detail.option.install_guide,'option_install_guide','安装指南','option_install_guide')">
                            {{ uploadBtnText(eCatalogPdfName) }}
                          </el-button>
                          <span v-if="detail.option?.install_guide?.data?.length > 0" class="h-badge">{{ detail.option?.install_guide?.data?.length }}</span>
                        </div>
                      </el-form-item>
                    </el-col>
                    <el-col :span="9">
                      <!-- 走线图 -->
                      <el-form-item label="走线图" label-width="100px">
                        <div style="position: relative;display:flex;justify-content: center;border:1px solid #DCDFE6;">
                          <el-button type="text" style="width: 100%;padding: 0 20px;line-height:40px;"
                            @click.stop="openImagePreview1(index,tabIndex,detail.option.wiring_diagram,'option_wiring_diagram','走线图','option_wiring_diagram')">
                            {{ uploadBtnText(eCatalogPdfName) }}
                          </el-button>
                          <span v-if="detail.option?.wiring_diagram?.length > 0" class="h-badge">{{ detail.option?.wiring_diagram?.length }}</span>
                        </div>
                      </el-form-item>
                    </el-col>
                  </el-row>
                  
                </el-col>
                
                <!-- 两列布局 col-2 -->
                <el-col :span="10" style="padding-left:40px;">
                  <el-row>
                    <el-col :span="24">
                      <!-- 商品规格 -->
                      <el-form-item :label="lang.goods.title_spec" required label-width="120px">
                        <div style="display: flex;flex-direction: row;">
                          <el-tooltip :content="lang.goods.goods_length_tips" placement="bottom">
                            <div style="display: flex;flex-direction: row;">
                              <el-input v-model="detail.option.length" :ref="'length_' + index + '_' + tabIndex" :placeholder="lang.goods.goods_length_tips1" :min="0" type="number" oninput="if(value<0)value=0" />
                              <div style="margin: 0 10px;">{{lang.goods.goods_length}}</div>
                            </div>
                          </el-tooltip>
                          <el-tooltip :content="lang.goods.goods_width_tips" placement="bottom">
                            <div style="display: flex;flex-direction: row;">
                              <el-input v-model="detail.option.width" :ref="'width_' + index + '_' + tabIndex" :placeholder="lang.goods.goods_width_tips1" :min="0" type="number" oninput="if(value<0)value=0" />
                              <div style="margin: 0 10px;">{{lang.goods.goods_width}}</div>
                            </div>
                          </el-tooltip>
                          <el-tooltip :content="lang.goods.goods_height_tips" placement="bottom">
                            <div style="display: flex;flex-direction: row;">
                              <el-input v-model="detail.option.height" :ref="'height_' + index + '_' + tabIndex" :placeholder="lang.goods.goods_height_tips1" :min="0" type="number" oninput="if(value<0)value=0" />
                              <div style="margin: 0 10px;">{{lang.goods.goods_height}}</div>
                            </div>
                          </el-tooltip>
                        </div>
                      </el-form-item>
                    </el-col>
                  </el-row>

                  <!-- 包装尺寸 -->
                  <!-- <el-row>
                    <el-col :span="24">
                      <el-form-item :label="lang.goods.package_option" required label-width="120px">
                        <div style="display: flex;flex-direction: row;flex-wrap: wrap;">
                          <div v-for="(option, optionIndex) in detail.option.package_option" :key="'option_' + optionIndex" style="display: flex;flex-direction: row;padding-bottom:10px;position: relative;">
                            <div style="position: absolute;left: -40px;font-size: 12px;">包裹{{optionIndex+1}}</div>
                            <el-tooltip :content="lang.goods.goods_length_tips" placement="bottom">
                              <div style="display: flex;flex-direction: row;">
                                <el-input v-model="option.length" :ref="'optlength_' + index + '_' + tabIndex + '_' + optionIndex" @blur="calculatePackageVolume(index, tabIndex)" :placeholder="lang.goods.goods_length_tips1" :min="0" type="number" oninput="if(value<0)value=0" />
                                <div style="margin: 0 10px;">{{lang.goods.goods_length}}</div>
                              </div>
                            </el-tooltip>
                            <el-tooltip :content="lang.goods.goods_width_tips" placement="bottom">
                              <div style="display: flex;flex-direction: row;">
                                <el-input v-model="option.width" :ref="'optwidth_' + index + '_' + tabIndex + '_' + optionIndex" @blur="calculatePackageVolume(index, tabIndex)" :placeholder="lang.goods.goods_width_tips1" :min="0" type="number" oninput="if(value<0)value=0" />
                                <div style="margin: 0 10px;">{{lang.goods.goods_width}}</div>
                              </div>
                            </el-tooltip>
                            <el-tooltip :content="lang.goods.goods_height_tips" placement="bottom">
                              <div style="display: flex;flex-direction: row;">
                                <el-input v-model="option.height" :ref="'optheight_' + index + '_' + tabIndex + '_' + optionIndex" @blur="calculatePackageVolume(index, tabIndex)" :placeholder="lang.goods.goods_height_tips1" :min="0" type="number" oninput="if(value<0)value=0" />
                                <div style="margin: 0 10px;">{{lang.goods.goods_height}}</div>
                              </div>
                            </el-tooltip>
                          </div>
                        </div>
                      </el-form-item>
                    </el-col>
                  </el-row> -->

                  <el-row>
                    <el-col :span="24">
                      <!-- 材质说明 -->
                      <el-form-item label="材质说明" required label-width="120px">
                        <el-input v-model="detail.option.structure" :ref="'structure_' + index + '_' + tabIndex" :rows="8" type="textarea" style="width: 94%;" placeholder="请输入商品结构、配件及材质说明" />
                      </el-form-item>
                    </el-col>
                  </el-row>
                </el-col>
              </el-row>
              <div class="h-operate">
                <div v-if="!pasted[index + '_' + tabIndex]" @click="onPasteSpec(index, tabIndex)" class="h-button1"><i class="local-iconfont icon-paste"></i> 粘贴</div>
                <div v-if="pasted[index + '_' + tabIndex]" class="h-button1"><i class="el-icon-check"></i> 已粘贴</div>
                <div v-if="!copied[index + '_' + tabIndex]" @click="onCopySpec(index, tabIndex, detail.option)" class="h-button1"><i class="local-iconfont icon-copy"></i> 复制</div>
                <div v-if="copied[index + '_' + tabIndex]" class="h-button1"><i class="el-icon-check"></i> 已复制</div>
              </div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </div>
      <input type="file" ref="imageInput"  accept=".png,.jpg,.jpeg" @change="onImageInputChange" style="display: none;"/>

      <!-- 上传文件组件 -->
      <el-upload
        :action="uploadUrl"
        :data="uploadParams"
        ref="upload"
        :accept="imageInput_el_accept"
        :on-success="handleSuccess"
 
        :on-exceed="handleExceed"
        :on-preview="handlePreview"
        :before-upload="beforeUpload"
        :on-progress="handleProgress"
        :on-change="handleChange"
        :auto-upload="imageInput_el_autoUpload"
        :show-file-list="false"
        :multiple="imageInput_el_multiple"
      >
        <div ref="imageInput_el" style="dispose:none;"></div>
      </el-upload>

      <!-- 上传文件 cad、3dMax -->
      <el-upload
        :action="uploadUrl"
        ref="uploadFile"
        :accept="refFileUpload_accept"
        :on-success="handleSuccess"
       
        :on-exceed="handleExceed"
        :on-preview="handlePreview"
        :on-progress="handleProgress"
        :show-file-list="false"
        :multiple="imageInput_el_multiple"
        :on-change="handleChange"
        :auto-upload="false"
      >
        <div ref="refFileUpload" style="dispose:none;"></div>
      </el-upload>

      <!-- 上传文件 glb 模型 -->
      <el-upload
        :action="uploadUrl"
        ref="uploadGlbRef"
        accept=".glb"
        :on-success="handleSuccessGlb"
        :show-file-list="false"
        :multiple="false"
      >
        <div ref="uploadGlbBtnRef" style="dispose:none;"></div>
      </el-upload>
    </div>
  </el-form>

  <!-- 批量设置的弹窗 -->
  <el-dialog :title="lang.goods.batch_set" :visible.sync="batchSetDialogVisible" width="30%">
    <el-form :model="batchSetForm" label-position="left" label-width="120px">
      <!-- 零售价格 -->
      <el-form-item :label="lang.goods.spec_product_price">
        <el-input v-model="batchSetForm.product_price" :min="0" type="number" oninput="if(value<0)value=0"
          :placeholder="lang.goods.batch_product_price_tips" style="width: 70%;">
        </el-input>
        <el-button type="primary" @click="applySingleBatchSet('product_price')">{{common_lang.confirm}}</el-button>
      </el-form-item>
      <!-- 商品型号 -->
      <el-form-item label="商品型号">
        <el-input v-model="batchSetForm.product_model" placeholder="批量设置商品型号" style="width: 70%;">
        </el-input>
        <el-button type="primary" @click="applySingleBatchSet('product_model')">{{common_lang.confirm}}</el-button>
      </el-form-item>
      <!-- 商品规格 长度 -->
      <el-form-item :label="lang.goods.title_spec_length">
        <el-input v-model="batchSetForm.length" :min="0" type="number" oninput="if(value<0)value=0"
          :placeholder="lang.goods.batch_length_tips" style="width: 70%;">
        </el-input>
        <el-button type="primary" @click="applySingleBatchSet('length')">{{common_lang.confirm}}</el-button>
      </el-form-item>
      <!-- 商品规格 宽度 -->
      <el-form-item :label="lang.goods.title_spec_width">
        <el-input v-model="batchSetForm.width" :min="0" type="number" oninput="if(value<0)value=0"
          :placeholder="lang.goods.batch_width_tips" style="width: 70%;">
        </el-input>
        <el-button type="primary" @click="applySingleBatchSet('width')">{{common_lang.confirm}}</el-button>
      </el-form-item>
      <!-- 商品规格 高度 -->
      <el-form-item :label="lang.goods.title_spec_height">
        <el-input v-model="batchSetForm.height" :min="0" type="number" oninput="if(value<0)value=0"
          :placeholder="lang.goods.batch_height_tips" style="width: 70%;">
        </el-input>
        <el-button type="primary" @click="applySingleBatchSet('height')">{{common_lang.confirm}}</el-button>
      </el-form-item>
      <!-- 商品重量（KG） -->
      <!-- <el-form-item :label="lang.goods.goods_weight"> -->
        <!-- <el-input v-model="batchSetForm.weight" :min="0" type="number" oninput="if(value<0)value=0" -->
          <!-- :placeholder="lang.goods.batch_weight_tips" style="width: 70%;"> -->
        <!-- </el-input> -->
        <!-- <el-button type="primary" @click="applySingleBatchSet('weight')">{{common_lang.confirm}}</el-button> -->
      <!-- </el-form-item> -->
      <!-- 包装件数 -->
      <el-form-item :label="lang.goods.package_number">
        <el-input v-model="batchSetForm.package_number" type="number" @blur="handlePackageNumberBlur"
          :placeholder="lang.goods.batch_package_number_tips" style="width: 70%;">
        </el-input>
        <el-button type="primary" @click="applySingleBatchSet('package_number')">{{common_lang.confirm}}</el-button>
      </el-form-item>
      <!-- 包装体积 -->
      <el-form-item label="包装体积">
        <el-input v-model="batchSetForm.volume" :min="0" type="number" oninput="if(value<0)value=0"
          placeholder="批量设置包装体积" style="width: 70%;">
          
        </el-input>
        <el-button type="primary" @click="applySingleBatchSet('volume')">{{common_lang.confirm}}</el-button>
      </el-form-item>
    </el-form>
  </el-dialog>

  <!-- 复制设置弹窗 -->
  <el-dialog title="复制设置" :visible.sync="copySettingsVisible" width="30%" append-to-body>
    <div>
      <div class="mb-10" style="margin-bottom:10px;">
        <el-checkbox
          :indeterminate="isIndeterminate"
          v-model="copySetCheckAll"
          @change="onCheckAllChange"
        >
          全选
        </el-checkbox>
        <el-button type="text" @click="resetCopySettings" style="margin-left: 10px">
          恢复默认
        </el-button>
      </div>

      <el-checkbox-group v-model="copySelectedFields" @change="onFieldChange">
        <el-checkbox
          v-for="item in copyFieldOptions"
          :key="item.value"
          :label="item.value"
          style="margin: 6px 12px 6px 0;"
        >
          {{ item.label }}
        </el-checkbox>
      </el-checkbox-group>
    </div>

    <span slot="footer" class="dialog-footer">
      <el-button @click="copySettingsVisible = false">取 消</el-button>
      <el-button type="primary" @click="saveCopySettings">保 存</el-button>
    </span>
  </el-dialog>


  <!-- 选择关联商品 -->
  <el-dialog :visible.sync="goods_show" width="60%" center top="6vh" :title="lang.goods.choose_relation_goods">
    <div>
      <div>
        <el-input v-model="goods_keyword" style="width:70%"></el-input>
        <el-button type="primary" @click="searchGoods(1)">搜索</el-button>
      </div>
      <!-- <div v-show="goods_info.good_names.length>0">
        <el-tag v-for="(tag,index) in goods_info.good_names" :key="'goodname_' + index" closable @close="closeGoods(index)" style="margin: 15px 5px 0 0;">
          {{tag}}
        </el-tag>
      </div> -->
      <el-table :data="goods_info.goods_list" style="width: 100%;height:500px;overflow:auto;margin-top:15px;" v-loading="loading">
        <el-table-column label="ID" prop="id" align="center" width="100px"></el-table-column>
        <el-table-column label="图片">
          <template slot-scope="scope">
            <div v-if="scope.row" style="display:flex;align-items: center">
              <img :src="scope.row.thumb_url"  style="width:80px;height:80px"/>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="商品信息">
          <template slot-scope="scope">
            <div v-if="scope.row" style="display:flex;align-items: center">
              <div style="margin-left:10px">{{scope.row.title}}</div>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column label="状态">
          <template slot-scope="scope">
            <el-tag type="success" v-if="scope.row.status == 1">上架</el-tag>
            <el-tag type="danger" v-if="scope.row.status == 0">下架</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="金额">
          <template slot-scope="scope">
          ￥{{scope.row.price}}
          </template>
        </el-table-column>

        <el-table-column label="品牌">
          <template slot-scope="scope">
          {{scope.row?.supplier_goods?.store_name ?? ''}}
          </template>
        </el-table-column>

        <el-table-column label="关联">
          <template slot-scope="scope">
            <el-checkbox v-if="form.goods.id != scope.row.id && scope.row.supp_id == form.goods.supp_id"
              v-model="scope.row.is_bidirectional" 
              :true-label="1" 
              :false-label="0"
              @change="handleRelationChange(scope.row)"
            >
              相互关联
            </el-checkbox>
          </template>
        </el-table-column> 

        <el-table-column prop="refund_time" label="操作" align="center" width="320">
          <template slot-scope="scope">
            <div v-if="form.goods.id !== scope.row.id">
              <el-button v-if="scope.row.is_relation !== -1" @click="cancelGoods(scope.row)">
                取消关联
              </el-button>
              <!-- 否则显示 "关联" 按钮 -->
              <el-button v-else @click="chooseGoods(scope.row)">
                关联
              </el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </div>
    <el-row style="background-color:#fff;">
      <el-col :span="24" align="center" migra style="padding:15px 5% 15px 0" >
        <el-pagination background  @current-change="searchGoods"
          layout="prev, pager, next"
          :page-size="Number(goods_info.page_size)" :current-page="goods_info.current_page" :total="goods_info.page_total"></el-pagination>
      </el-col>
    </el-row>
    <span slot="footer" class="dialog-footer">
      <el-button @click="goods_show = false" >确定</el-button>
    </span>
  </el-dialog>

  <!-- 多张图片预览 -->
  <el-dialog :visible.sync="imagePreviewVisible" width="90%" top="20px" class="dialog-class" :close-on-click-modal="false">
    <template slot="title"><div class="h-title">{{ previewTitle }}</div></template>
    <div class="h-preview">
      <div class="h-preview-img">
        <div class="h-img">
          <el-image :src="currentPreviewImg?.main_thumb || currentPreviewImg?.image_link || currentPreviewImg?.thumb_link" fit="contain" >
            <div slot="error" style="height:100%;display:flex;align-items: center;justify-content:center;">
              <i class="el-icon-picture-outline" style="font-size:48px"></i>
            </div>
          </el-image>
        </div>
        <div class="h-preview-tips">已上传 {{ previewImgList?.length }} 张图片</div>
      </div>
      <div class="h-preview-list">
       <draggable v-model="previewImgList" v-bind="dragOptions" direction="horizontal" class="horizontal-list" item-key="thumb_link">
        <div v-for="(preimg, itemIndex) in previewImgList" :key="preimg.thumb_link"
         @click="setCurrentPreviewImg(preimg)"
         class="h-image-box">
          <el-image :src="preimg?.image_link || preimg?.thumb_link" fit="contain" :class="preimg==currentPreviewImg?'h-preview-item-sel':'h-preview-item'" />
          <!-- <div class="h-img-title h-text-overflow">tup.jpg</div> -->
          <div @click.stop="removeUploadImage(itemIndex)" class="h-icon-close2"><i class="el-icon-close"></i></div>
          <div v-if="formFieldName=='atlas'" @click.stop="setPPT(preimg)" class="h-icon-ppt" :class="{'h-icon-ppt-sel':preimg.inPpt=='1'}">PPT</div>
        </div>
         </draggable>
         
        <!-- 上传 -->
        <div class="py-10">
          <div class="upload-img-small-boxed" @click="openImageInput(1,img_type,['atlas','maintenance_doc','option_install_guide'].includes(formFieldName))">
            <div class="icon-boxed mt-15">
              <div class="el-icon-plus"></div>
            </div>
            <div class="upload-img-tips">建议尺寸: 640 * 640</br>或正方型图片</div>
          </div>
        </div>
      </div>
    </div>
    <template slot="footer" class="dialog-footer">
      <el-button @click="onImagePreivewClose">返回</el-button>
    </template>
  </el-dialog>

<!-- 旧代码 -->
<!-- 上传文件组件 -->
  <upload-multimedia-img
    :upload-show="showSelectMaterialPopup"
    :type="materialType"
    :name="formFieldName"
    :selNum="selNum"
    :select="select"
    :isHigh="is_high"
    :upload_type="upload_type"
    @replace="uploadmediaClose"
    @sureSelect="sureSelectImg"
    @sure="selectedMaterial"
  ></upload-multimedia-img>
  <introduce v-model="showIntroduce" v-show="showIntroduce"></introduce>

  <pdf-viewer :pdfPath="pdfPath" :pdf_page="pdf_page" @replace="handlePdfClose" :showPdf="showPdf" @savePages="savePdfPage"></pdf-viewer>

  <three-stage :visible.sync="threeVisible" @save="onThreeSave" :d3ModelData="d3ModelData" :baseParts="baseModelParam"></three-stage>
  
  <!-- 文件上传 -->
   <div v-if="visible || pdfVisible" class="loading-overlay">
            <div class="loading-content">
              <div v-if="visible">
                <p>file Uploading...</p>
                <el-progress :show-text="false" :stroke-width="20" :percentage="uploadProgress"></el-progress>
                <p>{{uploadProgress}}%</p>
              </div>
               <div  v-if="pdfVisible">
                <p>PDF Reading...</p>
                <el-progress :show-text="false" :stroke-width="20" :percentage="pdfProgress"></el-progress>
                <p>{{pdfProgress}}%</p>
              </div>
            </div>
        </div>

         <!-- PDF读取 -->
         <!--
          <div v-if="pdfVisible" class="loading-overlay">
            <div class="loading-content">
                <p>PDF Reading...</p>
                <el-progress :show-text="false" :stroke-width="20" :percentage="pdfProgress"></el-progress>
                <p>{{pdfProgress}}%</p>
            </div>
        </div>
        -->
  
</div>

`,
  style: `


.loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
    }

.loading-content {
    text-align: center;
    color: white;
    width:150px;
}
.error-border input {
  border: 1px solid red !important; /* 红色边框 */
}

.h-spec-title {
  font-size: 15px;
  font-weight: bold;
  margin-top: 30px;
}
.h-spec-box {
  position: relative;
  border: 1px solid #29BA9C; /* #D9D9D9 */
  border-radius: 3px;
  margin-top:10px;
  padding: 20px 20px 30px 20px;
}
.h-form-spec-title {
  width: 35%;
  position: relative;
}
.h-icon-circle-close {
  position:absolute;
  right: -9px;
  top: -7px;
  font-size: 20px;
  color: #707070;
  cursor:pointer;
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
.h-text-overflow {
  white-space: nowrap; /* 不换行 */
  overflow: hidden; /* 隐藏超出的内容 */
  text-overflow: ellipsis; /* 用省略号表示被隐藏的部分 */
  /* max-width: 200px; 设置最大宽度以限制文本的显示长度 */
}

.h-popover {
  height: 300px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}
.h-popover .el-image {
  width: 100%;
  height: 100%;
}
.h-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  display: flex;
  align-items: center;
  padding: 0 6px;
  font-size: 12px;
  height: 18px;
  line-height: 1;
  border-radius: 10px;
  background-color: #f56c6c;
  color: #fff;
  cursor: pointer;
}

.h-color-display-wrapper {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  background-color: #FBFAF8;
  padding: 10px;
  cursor: pointer;
}
.h-color-module {
  display: flex;
  align-items: center;
  margin-right: 20px;
}
.h-module-name {
  font-size: 13px;
  margin-right: 10px;
  color: #999999;
}
.h-color-list {
  display: flex;
  margin-left: 10px;
}
.h-color-circle {
  margin-left: -10px;
  width: 35px;
  height: 35px;
  border-radius: 50%;
  background-size: cover;
  background-position: center;
  border: 1px solid #dcdcdc;
}
.h-progress {
  padding: 6px 0;
  background-color: #FBFAF8;
}
.h-progress-row {
  padding-left: 10px;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}
.h-progress-label {
  font-size: 13px;
  line-height: 1;
  color: #999;
}

/* 多张图片预览组件样式 */
.h-preview {
  display: flex;
  flex-direction: column;
}
.h-title {
  border-left: 4px solid #29ba9c;
  padding-left: 15px;
  font-size: 20px;
  font-weight: bold;
  color: #29ba9c;
}
.h-preview-img {
  position: relative;
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}
.h-preview-img .h-img {
  width: 600px;
  height: 600px;
  box-shadow: 0px 2px 6px 0px rgba(68, 68, 68, 0.3);
}
.h-preview-img .h-img .el-image {
  width: 100%;
  height: 100%;
}
.h-preview-tips {
  position: absolute;
  right: 30px;
  bottom: 0;
  color: #808080;
  font-size: 16px;
}
.h-preview-list {
  display: flex;
  gap: 20px;
  padding: 0 20px;
  border-top: 1px solid #bbb;
  overflow-x: auto;
}
.h-preview-list .h-image-box {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 10px 0;
  cursor: pointer;
}
.h-preview-list .h-image-box img {
  width: 120px;
  height: 120px;
}
.h-preview-list .h-image-box .h-img-title {
  color: #808080;
  font-size: 14px;
}
.h-preview-item-sel{
  border:2px solid #dddddd;
}
.h-preview-item{
  border:2px solid transparent;
}

.h-preview-list .h-image-box .h-icon-close2 {
  position: absolute;
  top: 10px;
  right: 0;
  width: 16px;
  height: 16px;
  display: flex;
  justify-content: center;
  align-items: center;
  color: #fff;
  background-color: #5b6b73;
  cursor: pointer;
}

.h-icon-ppt{
  position: absolute;
  top: 10px;
  left: 0;
  display: flex;
  justify-content: center;
  align-items: center;
  color: #fff;
  background-color: #dbdbdb;
  cursor: pointer;
  padding:2px;
  border-radius: 2px;
  font-size:12px;
  height:16px;
}

.h-icon-ppt-sel{
  background-color: #F68517;
}


.dialog-class .el-dialog {
  margin-bottom: 20px;
}
.dialog-class .el-dialog__header {
  padding: 20px 25px 10px 25px;
}
.dialog-class .el-dialog__body {
  padding: 0;
}
.dialog-class .el-dialog__footer {
  padding: 10px 20px 10px 20px;
}


/* 组件样式 */
.h-baseinfo-content .el-radio {
  margin-right: 25px;
}
.h-baseinfo-content .el-radio:last-child {
  margin-right: 0;
}
.h-form-content>.el-form-item__content {
  display: flex;
}

.horizontal-list {
  display: flex;
  justify-content: flex-start;
  align-items: center;
  gap: 20px;
}

/* 商品规格值样式 */
.specvalue-area {
  padding: 10px 0 10px 10px;
  overflow-x: auto;
}
#basicGoods ::-webkit-scrollbar {
  height: 6px;
  background-color: #fff;
}
#basicGoods ::-webkit-scrollbar-track {
  box-shadow: inset 0 0 6px rgba(0, 0, 0, 0);
  background-color: #fff;
}
#basicGoods ::-webkit-scrollbar-thumb {
  box-shadow: inset 0 0 6px rgba(0, 0, 0, 0);
  background-color: #bbb;
  border-radius: 10px;
}
  
.three-warning{
  color:red;
  position:absolute;
  width:20px;
  height:20px;
  font-size:18px;
  display:flex;
  justify-content:center;
  align-items: center;
  top:-10px;
  right:-10px;
}


/* 旧代码 */
#basicGoods input::-webkit-outer-spin-button,
#basicGoods input::-webkit-inner-spin-button {
  -webkit-appearance: none;
}
#basicGoods input[type="number"] {
  -moz-appearance: textfield;
}
`,
  props: {
    form: {
      default() {
        return;
      },
    },
    formKey: {
      type: String,
      default: "goods",
    },
    attr_hide: {
      default() {
        return {};
      },
    },
    lang: {
      default() {
        return {};
      },
    },
  },
  data() {
    let regular = new RegExp("^[0-9][0-9]*$");
    let checkoutSort = (rule, value, callback) => {
      this.sortRegular = true;
      let sort = regular.test(this.form.goods.display_order);
      if (!sort) {
        callback(new Error(this.lang.goods.error_positive_integer));
        this.sortRegular = false;
      }
    };
    let checkoutTitle = (rule, value, callback) => {
      this.titleRegular = true;
      if (this.nationList.length) {
        for (let key in this.form.goods.lang) {
          if (!this.form.goods.lang[key].title) {
            callback(new Error(this.lang.goods.error_title_empty));
            this.titleRegular = false;
          }
        }
      } else if (!this.form.goods.title) {
        callback(new Error(this.lang.goods.error_title_empty));
        this.titleRegular = false;
      }
    };
    // let checkoutSku = (rule, value, callback) => {
    //   let sku = true
    //   if(this.form.goods.sku !== '个' || this.form.goods.sku !== '件' || this.form.goods.sku !== '包'){
    //     sku = false
    //   }else{
    //     sku = true
    //   }
    //   this.skuRegular = true
    //   if(!sku){
    //     callback(new Error('个/件/包'));
    //     this.skuRegular = false
    //   }
    // };
    let checkoutCategory = (rule, value, callback) => {
      if (!this.attr_hide.hide_category) {
        let info = true;
        this.categoryList.forEach((item) => {
          item.forEach((el) => {
            if (!el.id) {
              info = false;
              return;
            }
          });
        });
        this.categoryRegular = true;
        if (!info) {
          callback(new Error(this.lang.goods.error_category_empty));
          this.categoryRegular = false;
        }
      } else {
        this.categoryRegular = true;
      }
    };
    // let checkoutThumb = (rule, value, callback) => {
    //   this.thumbRegular = true;
    //   if (!this.form.goods.thumb) {
    //     callback(new Error(this.lang.goods.error_thumb_empty));
    //     this.thumbRegular = false;
    //   }
    // };
    // let checkoutMainUrl = (rule, value, callback) => {
    //   this.thumbRegular = true;
    //   if (!this.form.goods.main_url || this.form.goods.main_url?.length == 0) {
    //     callback(new Error(this.lang.goods.error_thumb_empty));
    //     this.thumbRegular = false;
    //   }
    // };
    let checkoutVirtualSales = (rule, value, callback) => {
      let virtualSales = regular.test(this.form.goods.virtual_sales);
      this.virtualSalesRegular = true;
      if (!virtualSales) {
        callback(new Error(this.lang.goods.error_positive_integer));
        this.virtualSalesRegular = false;
      }
    };
    let checkoutStock = (rule, value, callback) => {
      let stock = regular.test(this.form.goods.stock);
      this.stockRegular = true;
      if (!stock) {
        callback(new Error(this.lang.goods.error_positive_integer));
        this.stockRegular = false;
      }
    };

    let urlParams = new URLSearchParams(window.location.search);
    let routeName = urlParams.get("route");

    return {
      selectedSpecDetailsIndex: "", //当前操作的specDetails的Index
      selectedSpecDetailTabsIndex: "", //当前操作的specDetail.modelTypes的Index
      upload_type: "thumb",
      readonly: readonly,
      showIntroduce: false,
      showSelectMaterialPopup: false,
      formFieldName: "",
      goodsImagesChangeIndex: null,
      materialType: "",
      selNum: "one",
      select: "open",
      goods_show: false,
      goods_keyword: "",
      loading: false,
      // 选择关联商品 组件交互数据
      goods_info: {
        goods_list: [], //列表的是否相互关联绑定属性 is_bidirectional: 1.双向关联 0.非双向关联(用于页面交互); is_relation: 1.双向关联 0.单向关联 -1.无关联(用于排序)
        page_total: 0,
        page_size: 20,
        current_page: 1,
        real_search_form: "",
        good_names: [],
        // goods_relation: [], //弃用 //属性is_relation: 1.双向关联 0.单向关联
      },
      rules: {
        sort: { validator: checkoutSort },
        title: { validator: checkoutTitle },
        category: { validator: checkoutCategory },
        // goodsSku:{validator:checkoutSku},
        // thumb: { validator: checkoutThumb },
        // main_url: { validator: checkoutMainUrl },
        virtualSales: { validator: checkoutVirtualSales },
        stock: { validator: checkoutStock },
      },
      submitPropertyKeyWhiteList: [],
      // yesRegular:true,
      categoryList: [],
      // 原始值
      OriginCategory: [],
      // 改变后的值
      changeCategoryList: [],
      // 分类值
      category_list_box: [],
      isShow: true,

      sortRegular: true,
      titleRegular: true,
      // skuRegular:true,
      categoryRegular: true,
      virtualSalesRegular: true,
      stockRegular: true,
      // thumbRegular: true,
      // 提交的数据

      ////新添加的data
      eCatalogPdfName: "", // 存储产品图册文件的名称
      maintenanceDocName: "", // 保养手册文件的名称
      installGuideName: "", // 安装指南文件的名称
      goodsSpecs: [], //商品规格
      specDetails: [], //商品规格详细信息
      batchSetDialogVisible: false, // 控制批量设置弹窗的显示
      batchSetForm: {
        // 批量设置的表单数据
        // market_price: "", // 商品原价
        product_price: "", // 零售价格
        // cost_price: "", // 成本价格
        product_model: "", // 商品型号
        length: "", // 商品 长
        width: "", // 商品 宽
        height: "", // 商品 高
        weight: "", // 商品重量
        package_number: 1, // 包装件数
        volume: "", // 包装体积
      },

      threeChildrensIsshow: true,
      secondChildrensIsshow: true,
      isDecorate: IsDecorate,
      common_lang: JSON.parse(common_lang),
      nationList: nationList,
      routeName: routeName,
      isAdd: true, //是否是新增
      craftMaterialsList: [], //工艺材质选项数据
      goodsStyleList: [], //商品风格

      //3D模型
      d3Show: false,
      //传值给3d模型页面的数据
      d3ModelData: {
        d3ModelUrl: null, //3D模型 url
        specDetailsIndex: "", //当前操作的specDetails的Index
        specDetailTabsIndex: "", //当前操作的specDetail.modelTypes的Index
        spec_item_id: "", //当前规格组合的id
        modelType: null, //模型类型 0:独立位(完整位)或十字型; 1:首位或T字型; 2:延伸位或F型; 3:尾位;
        option_id: "", //当前规格组合中option的id
        model_param: [], //模型部件参数
        default_param: [], //默认模型部件参数

        d3ModelUrl_ori: null, // 原始3D模型url *.glb
        d3MaxUrl: null, // 3DMAX 模型的URL，*.MAX、*.3ds、*.FBX、*.OBJ
      },
      pdfPath: null,
      showPdf: false,
      pdf_page: [],

      threeVisible: false,
      editOption: null,
      baseModelParam: [],

      imagePreviewVisible: false, // 控制图片预览弹窗的显示
      previewTitle: "图片", // 图片预览弹窗的标题
      previewImgList: [], // 当前预览的图片数组
      currentPreviewImg: "", // 当前预览的图片
      is_high: 0,
      goods_images: [],

      uploadUrl: uploadUrl,
      uploadParams: {
        thumb_type: "goodsmain",
        is_high: 1,
      },
      visible: false,
      uploadProgress: 0,
      img_type: "",

      imageInput_el_multiple: false,
      imageInput_el_accept: ".png,.jpg,.jpeg",
      imageInput_el_autoUpload: true,
      refFileUpload_accept: ".png,.jpg,.jpeg",

      dragOptions: {
        group: {
          name: "shared",
          pull: "clone",
          put: true,
        },
        animation: 200,
        dragClass: "dragging", // 拖拽时的样式类
        ghostClass: "ghost", // 占位符的样式类
        chosenClass: "chosen", // 选中项的样式类
        disabled: false,
      },

      pdfVisible: false,
      pdfProgress: 0,

      //根据商品规格选中项 显示对应的商品规格详细信息specDetails
      specDetailSelectedIndex: 0, //当前选中的specDetails的索引
      selectedPathIds: [], //从顶到叶的 id 数组，用来驱动子组件选中
      selectedTopIndex: 0, //选中的顶级规格值索引（用于子级缩进）

      //复制粘贴 商品规格数据
      copied: {}, // 用于控制每个 "已复制" 按钮的显示
      pasted: {}, // 用于控制每个 "已粘贴" 按钮的显示
      copySpecOptionData: {}, // 复制的商品规格数据
      // 复制设置
      copySettingsVisible: false, // 控制复制设置弹窗的显示
      storageCopyFieldsKey: "goods.copy.settings.fields", // localStorage Key
      copyFieldOptions: [
        { label: "商品单价", value: "product_price" },
        { label: "商品型号", value: "product_model" },
        { label: "商品规格 长", value: "length" },
        { label: "商品规格 宽", value: "width" },
        { label: "商品规格 高", value: "height" },
        { label: "包装件数", value: "package_number" },
        { label: "包装体积", value: "volume" },
        { label: "材质说明", value: "structure" },
        { label: "安装指南", value: "install_guide" },
        { label: "走线图", value: "wiring_diagram" },
        { label: "白底图", value: "thumb" },
        { label: "cad图纸", value: "cad_plan_model" },
        { label: "3DMax模型", value: "d3Max" },
        { label: "DIY模型", value: "d3model" },
        { label: "DIY部件", value: "model_param" },
      ],
      copySelectedFields: [], // 勾选项
      copySetCheckAll: true,
      isIndeterminate: false,

      // 人数位 数据
      singleTypeOptions: [
        { label: "单人位", value: 1 },
        { label: "双人位", value: 2 },
        { label: "三人位", value: 3 },
        { label: "四人位", value: 4 },
        { label: "五人位", value: 5 },
        { label: "六人位", value: 6 },
        { label: "七人位", value: 7 },
        { label: "八人位", value: 8 },
        { label: "九人位", value: 9 },
        { label: "十人位", value: 10 },
      ],

      threeWarningSign: 0,

      // 模型上传进度相关
      isPollingModelStatus: false, // 是否正在轮询
      modelStatusTimer: null, // 轮询定时器 id
      optionModelStatusMap: {}, // 每个规格 option 对应的模型同步状态
      modelStatusSummary: null, // 接口返回的 summary 汇总信息
    };
  },
  created() {
    if (!this.form.goods.lang) {
      this.$set(this.form.goods, "lang", {});
      for (let item of this.nationList) {
        this.$set(this.form.goods.lang, item.value, {
          title: "",
          alias: "",
        });
      }
    }
    // this.eCatalogPdfName = this.extractFileName(this.form.goods.e_catalog_pdf);
    // this.maintenanceDocName = this.extractFileName(
    //   this.form.goods.maintenance_doc
    // );
    // this.installGuideName = this.extractFileName(this.form.goods.install_guide);

    // 复制设置 初始化：从本地读取；若无设置，默认全选
    const saved = window.localStorage.getItem(this.storageCopyFieldsKey);
    if (saved) {
      try {
        const list = JSON.parse(saved);
        this.copySelectedFields = Array.isArray(list) ? list : [];
      } catch (e) {
        this.copySelectedFields = [];
      }
    }
    // 无设置或解析为空 -> 默认勾选除 product_price 的所有字段
    if (!this.copySelectedFields.length) {
      this.copySelectedFields = this.getDefaultCopyFields();
    }
    this.syncCheckAllState();
  },
  mounted() {
    this.addDefaul();
    // 设置默认品牌
    if (this.form.goods.brand_id === 0) {
      this.form.goods.brand_id = "";
    }
    //判断是否是新增
    if (this.form.goods.id) this.isAdd = false;

    this.goodsCategoryShow(); // 商品分类回显
    this.getCategoryValue(); // 获取分类值
    // 赋值动态分类选项数据
    if (this.form.category_level >= 2) {
      this.onFocusSecond(0, this.categoryList[0][0]);
    }
    if (this.form.category_level >= 3) {
      this.onFocusThree(0, this.categoryList[0][1], this.categoryList[0][0]);
    }

    // 判断是否有规格组合并初始化
    console.log("====form.goods====", this.form.goods);
    console.log("====form.option====", this.form.option);
    if (
      this.form.option &&
      this.form.option.specs &&
      this.form.option.specs.length > 0
    ) {
      let productType = Number(this.form.goods?.productType ?? 5);
      productType = productType === 1 ? 5 : productType;
      this.initSpecifications(this.form.option, productType);
    } else {
      this.addDefaultSpecification();
    }
    //初始化，绑定已关联商品
    if (!this.form.goods.hasOwnProperty("goods_relation")) {
      this.$set(this.form.goods, "goods_relation", []);
    }
    if (!this.isArrayEmpty(this.form.goods.related_goods_name)) {
      this.goods_info.good_names.push(...this.form.goods.related_goods_name);
    }
    // console.log("====goods_relation====", this.form.goods.goods_relation);
    this.$forceUpdate();

    //
    this.fetchGoodsStyle();

    // 兼容 main_url 之前是一个对象
    // if (
    //   Object.prototype.toString.call(this.form.goods.main_url) ===
    //     "[object Object]" &&
    //   this.form.goods.main_url.constructor === Object
    // ) {
    //   const obj = JSON.parse(JSON.stringify(this.form.goods.main_url));
    //   this.form.goods.main_url = [obj];
    // }

    // 模式时，启动模型状态轮询
    if (!this.isAdd && this.form.goods && this.form.goods.id) {
      this.startPollModelStatus(); //启动模型状态轮询
    }
  },
  computed: {
    // 商品图册
    eCatalogPdfNameShow() {
      if (this.eCatalogPdfName) return this.eCatalogPdfName;
      else return this.lang.goods.upload_file_tips1;
    },
    // 保养手册
    maintenanceDocNameShow() {
      if (this.maintenanceDocName) return this.maintenanceDocName;
      else return this.lang.goods.upload_file_tips1;
    },
    // 安装指南
    installGuideNameShow() {
      if (this.installGuideName) return this.installGuideName;
      else return this.lang.goods.upload_file_tips1;
    },
    // 商品分类：判断是否有二级分类
    isLevel2() {
      return this.form.category_level
        ? this.form.category_level * 1 == 2
        : false;
    },
    // 商品分类：判断是否有三级分类
    isLevel3() {
      return this.form.category_level
        ? this.form.category_level * 1 == 3
        : false;
    },
    // 商品主图
    // hasMainUrl() {
    //   return this.form.goods.main_url?.length > 0;
    // },
    // 商品效果图
    hasThumbUrl() {
      return this.form.goods.thumb_url?.length > 0;
    },
    // 商品实拍图
    hasRealImage() {
      return this.form.goods.real_image?.length > 0;
    },
    // 走线示意图
    hasWiringDiagram() {
      return this.form.goods.wiring_diagram?.length > 0;
    },
    threeWarning() {
      // 标识，用作触发该计算属性
      console.log("===threeWarning sign===", this.threeWarningSign);

      let warnings = [];

      const specItem = this.specDetails[this.selectedTopIndex];

      if (!specItem) return warnings;

      const { activeTab, modelTypes } = specItem;

      const modelType = modelTypes.find((x) => x.modelType == activeTab);

      if (!modelType) return warnings;

      const option = modelType.option;

      console.log("===threeWarning===", option);

      if (!option) return warnings;

      if (!option.d3ModelUrl_ori && !option.d3ModelUrl_ori_file) {
        warnings.push("未上传glb模型");
      }

      if (!option.thumb3dModelUrl && !option.thumb3dModelUrl_file) {
        warnings.push("未上传减面的glb模型");
      }

      if (option.total_face > 10000) {
        warnings.push(`减面的glb模型面数【${option.total_face}】超过10000`);
      }

      return warnings;
    },
  },
  methods: {
    // === 模型上传进度处理 ===
    // 开始轮询模型状态
    startPollModelStatus() {
      if (this.isPollingModelStatus) return; // 避免重复启动
      this.isPollingModelStatus = true;
      this.pollOptionStatus();
    },
    // 定时查询模型状态
    async pollOptionStatus() {
      try {
        if (!this.form.goods || !this.form.goods.id) return;

        // 模型同步状态接口
        const res = await this.$http.get(
          pollGoodsUrl + "&goods_id=" + this.form.goods.id + "&mock=0"
        ); // mock:1.测试 0.正式

        const statusData = res && res.data && res.data.data;
        if (!statusData) return;

        // 更新 UI 显示状态
        this.updateModelStatusDisplay(statusData);

        const summary = statusData.summary || {};
        const hasProcessing = Number(summary.processing || 0) > 0; //同步中
        const hasPending = Number(summary.pending || 0) > 0; //等待同步

        // 只要还有 processing 或 pending 就继续轮询
        if (hasProcessing || hasPending) {
          this.modelStatusTimer = setTimeout(() => {
            this.pollOptionStatus();
          }, 5000);
        } else {
          console.log("所有模型同步任务已结束（完成或失败）");
          this.isPollingModelStatus = false;
          this.modelStatusTimer = null;
        }
      } catch (error) {
        console.error("轮询模型状态失败:", error);
        // 出错也可以稍后重试
        this.modelStatusTimer = setTimeout(() => {
          this.pollOptionStatus();
        }, 10000);
      }
    },
    // 更新模型状态显示，并建立 option.id -> 状态 的映射
    updateModelStatusDisplay(statusData) {
      // console.log("模型同步状态更新:", statusData);
      this.modelStatusSummary = statusData.summary || null;

      const optionStatusMap = {};
      const optionsStatus = statusData.options || {};

      Object.keys(optionsStatus).forEach((optionIdStr) => {
        const typeMap = optionsStatus[optionIdStr] || {};
        const typeStatusList = Object.keys(typeMap).map(
          (typeKey) => typeMap[typeKey]
        );

        let hasCompleted = false; //同步完成
        let hasProcessing = false; //同步中
        let hasFailed = false; //同步失败
        let hasPending = false; //等待同步

        let totalProgress = 0;
        let count = 0;

        // 一个option有多个模型
        typeStatusList.forEach((st) => {
          const status = st.status;
          if (status === "completed") hasCompleted = true; //同步完成
          else if (status === "processing") hasProcessing = true; //同步中
          else if (status === "failed") hasFailed = true; //同步失败
          else if (status === "pending") hasPending = true; //等待同步

          if (st.progress != null) {
            const num = parseInt(String(st.progress).replace("%", ""), 10);
            if (!isNaN(num)) {
              totalProgress += num;
              count++;
            }
          }
        });

        const avgProgress = count > 0 ? Math.round(totalProgress / count) : 0;

        // 约定整体状态优先级：failed > processing|pending > completed
        let overallStatus = "pending";
        if (hasFailed) overallStatus = "failed";
        else if (hasProcessing || hasPending) overallStatus = "processing";
        // else if (hasPending) overallStatus = "pending";
        else if (hasCompleted && !hasProcessing && !hasFailed) {
          overallStatus = "completed";
        }

        optionStatusMap[String(optionIdStr)] = {
          raw: typeMap, // 各 type 的原始数据
          overallStatus, // "completed" | "processing" | "failed" | "pending"
          overallProgress: avgProgress, // 用于进度条显示
        };
      });

      // 一次性更新
      this.optionModelStatusMap = optionStatusMap;
      console.log("option对应的进度数据", this.optionModelStatusMap);
    },
    // 根据 option.id 获取模型同步信息
    getOptionModelStatus(optionId) {
      if (!optionId || !this.optionModelStatusMap) return null;
      const info = this.optionModelStatusMap[String(optionId)];
      if (!info) return null;

      const { overallStatus, overallProgress, raw } = info;

      let label = "等待同步";
      let progress = overallProgress || 0;
      let status = undefined; // 进度条组件的 status

      if (overallStatus === "completed") {
        label = "模型同步完成";
        progress = 100;
        status = "success";
      } else if (overallStatus === "processing") {
        label = "模型同步中";
        // 进度直接用接口平均值
      } else if (overallStatus === "failed") {
        label = "模型同步失败";
        status = "exception";
      } else if (overallStatus === "pending") {
        label = "等待同步";
      }

      return {
        label, // 中文文案
        progress, // 0-100
        status, // 进度条组件的状态："success" | "exception" | undefined
        overallStatus, // 原始状态，用于逻辑判断
        detail: raw, // 各 type 的具体数据
      };
    },
    // 校验某个 option 对应的模型是否“全部完成” (暂时没用到)
    isOptionModelReady(optionId) {
      const info = this.getOptionModelStatus(optionId);
      if (!info) {
        // 没有任何同步任务记录时，不强制拦截
        return true;
      }
      return info.overallStatus === "completed";
    },
    // === 模型上传进度处理 end ===

    // 上传/修改文件
    uploadBtnText(value) {
      return value.length > 0
        ? this.lang.goods.upload_file_text1
        : this.lang.goods.upload_file_text;
    },
    editPageCount() {
      this.showPdf = true;
      this.pdf_page = this.form.goods.pdf_page;
      this.pdfPath = this.pdfPath
        ? this.pdfPath
        : this.form.goods.e_catalog_pdf_url;
    },
    handlePdfClose() {
      this.showPdf = false;
    },
    //保存pdf的页码
    savePdfPage(pages) {
      this.showPdf = false;
      this.form.goods.pdf_page = pages;
    },
    //校验商品名称
    validateTitle() {
      if (this.form.goods.title.length > 20) {
        this.$message.error(this.lang.goods.error_title_tips1);
        return false;
      }
      return true;
    },
    async fetchGoodsStyle() {
      this.craftMaterialsList = await this.getGoodsStyle(2); //工艺材质
      this.goodsStyleList = await this.getGoodsStyle(1); //商品风格
    },
    // 获取 商品风格和 工艺材质
    async getGoodsStyle(type) {
      this.loading = true;
      try {
        const res = await this.$http.get(goodsstyle_url + "&type=" + type);
        if (res) {
          this.loading = false;
          return res.data.data;
        } else {
          console.log(res);
          this.loading = false;
          return [];
        }
      } catch (err) {
        console.log(err);
        this.loading = false;
        return [];
      }
    },
    ///// 图片预览弹窗功能
    // 图片预览弹窗显示
    openImagePreview(data, formFieldName, title, img_type) {
      this.img_type = img_type;
      this.previewTitle = title;
      this.formFieldName = formFieldName;
      this.uploadParams.thumb_type = "goodsmain";
      if (data?.length) {
        console.log("======Image Preview Data=======", data);
        this.imagePreviewVisible = true;
        this.previewImgList = data;
        this.currentPreviewImg = data[0];
      } else {
        this.openImageInput(1, img_type, true);
      }
    },

    // 商品图册，保养手册，安装指南
    openImagePreviewUpload(data, formFieldName, title, img_type, isHigh = 1) {
      this.img_type = img_type;
      this.formFieldName = formFieldName;
      this.previewTitle = title;
      this.uploadParams.thumb_type = "detail";

      const list = data?.data;

      if (list?.length) {
        console.log("======Image Preview upload Data=======", data);
        this.imagePreviewVisible = true;
        this.previewImgList = list;
        this.currentPreviewImg = list[0];
      } else {
        this.openImageInput(isHigh, img_type, true);
      }
    },

    // 规格组合明细-走线图
    openImagePreview1(index, tabIndex, data, formFieldName, title, img_type) {
      this.selectedSpecDetailsIndex = index;
      this.selectedSpecDetailTabsIndex = tabIndex;
      this.img_type = img_type;
      this.previewTitle = title;
      this.formFieldName = formFieldName;
      this.uploadParams.thumb_type = "goodsmain";
      if (data?.length) {
        console.log("======Image Preview Data=======", data);
        this.imagePreviewVisible = true;
        this.previewImgList = data;
        this.currentPreviewImg = data[0];
      } else {
        this.openImageInput(1, img_type, true);
      }
    },
    // 规格组合明细-安装指南
    openImagePreviewUpload1(
      index,
      tabIndex,
      data,
      formFieldName,
      title,
      img_type,
      isHigh = 1
    ) {
      this.selectedSpecDetailsIndex = index;
      this.selectedSpecDetailTabsIndex = tabIndex;
      this.img_type = img_type;
      this.formFieldName = formFieldName;
      this.previewTitle = title;
      this.uploadParams.thumb_type = "detail";

      const list = data?.data;

      if (list?.length) {
        console.log("======Image Preview upload Data=======", data);
        this.imagePreviewVisible = true;
        this.previewImgList = list;
        this.currentPreviewImg = list[0];
      } else {
        this.openImageInput(isHigh, img_type, true);
      }
    },

    // 设置当前预览的图片
    setCurrentPreviewImg(item) {
      console.log("currentPreviewImg: ", item);
      this.currentPreviewImg = item;
    },
    // 打开上传弹出
    openUploadPopup() {
      this.displaySelectMaterialPopup(this.formFieldName);
    },
    // 移除上传的图片
    removeUploadImage(itemIndex) {
      if (this.currentPreviewImg == this.previewImgList[itemIndex]) {
        this.currentPreviewImg =
          itemIndex == this.previewImgList.length - 1
            ? this.previewImgList[itemIndex - 1]
            : this.previewImgList[itemIndex + 1];
      }

      // 1. 根据 formFieldName 调用对应的删除方法
      if (this.formFieldName == "main") {
        this.removeMainImage(itemIndex);
      } else if (this.formFieldName == "other") {
        this.removeGoodsImage(itemIndex); // 移除商品效果图
      } else if (this.formFieldName == "real_image") {
        this.removeGoodsRealImage(itemIndex); // 移除商品实拍图
      } else if (this.formFieldName == "wiring_diagram") {
        this.removeWiringDiagramImage(itemIndex); // 移除走线示意图
      } else if (this.formFieldName == "atlas") {
        this.removeAtlasImage(itemIndex); // 商品图册
      } else if (this.formFieldName == "maintenance_doc") {
        this.removeMaintenanceImage(itemIndex); // 保养手册
      } else if (this.formFieldName == "install_guide") {
        this.removeInstallImage(itemIndex); // 安装指南
      } else if (this.formFieldName == "option_install_guide") {
        this.removeOptionInstallImage(itemIndex); // 规格组合明细-安装指南
      } else if (this.formFieldName == "option_wiring_diagram") {
        this.removeOptionWiringDiagramImage(itemIndex); // 规格组合明细-走线图
      }
    },

    setPPT(item) {
      item.inPpt = item.inPpt ? 0 : 1;
    },

    ///// end 图片预览弹窗功能
    // 打开上传/图片预览 弹窗
    openUploadOrPreview(hasImage, data, formFieldName, title) {
      if (hasImage) {
        this.openImagePreview(data, formFieldName, title);
      } else {
        this.displaySelectMaterialPopup(formFieldName);
      }
    },

    // 添加默认的规格组
    addDefaultSpecification() {
      const specId = `SC${this.goodsSpecs.length}`;
      this.goodsSpecs.push({
        id: specId,
        title: "款式", // 默认规格组名称为“尺寸”
        initialTitle: "款式",
        spec_item: [
          {
            id: `SV0&${specId}`, //id
            parent_id: 0, //父节点id
            parent_path_id: "", //“从顶级到父级”的上级id,用“_”拼接
            specid: specId, //规格组id
            productType: 5, //模型类型
            title: "1400*600*750", //标题
            initialTitle: "1400*600*750",
            level: 1, //节点层级
            order_index: 0, // 顺序下标
            sort: 0,
          },
        ], // 默认添加一个空规格值
      });
      this.generateCombinations(); // 排列组合并生成规格详细信息
    },
    // 添加规格组
    addSpecification() {
      return;
      // 检查是否存在空的规格组
      if (this.goodsSpecs.some((spec) => !spec.title?.trim())) {
        this.$message({
          message: "存在未填写的规格组，请先填写规格组名称",
          type: "warning",
        });
        return;
      }
      // 注意：option是SP   规格名SC  和规格值SV新增 前缀分开来 保证id唯一
      const specId = `SC${this.goodsSpecs.length}`;
      this.goodsSpecs.push({
        id: specId,
        title: "", // 规格组名称
        spec_item: [{ id: `SV0&${specId}`, specid: specId, title: "" }], // 规格组中的规格值
      });
    },
    // 添加规格值(同级)  specIndex:规格组Index; list:当前规格值数组(同级); depth:树层级
    addSiblingSpecValue(specIndex, list, depth) {
      const level = (list[0] && list[0].level) || depth || 1;
      const parentId = (list[0] && list[0].parent_id) || 0;
      const parentPathId = (list[0] && list[0].parent_path_id) || "";
      const orderIndex = this.getMaxOrderIndexWithin(list) + 1; // 取同级中最大的 order_index + 1

      list.push(
        this.createNode(
          specIndex,
          orderIndex,
          parentId,
          parentPathId,
          level,
          list.length
        )
      );
      // this.setSortForSiblings(list); //重新排序同级的 sort
    },
    // 添加子级规格值  specIndex:规格组Index; parentNode:父节点数据;
    addChildSpecValue(specIndex, parentNode) {
      const curLevel = parentNode.level || 1;
      if (curLevel >= 3) {
        this.$message.warning("最多支持 3 级规格值");
        return;
      }
      if (!Array.isArray(parentNode.children)) {
        this.$set(parentNode, "children", []);
      }

      // “从顶级到父级”的上级id用“_”拼接
      const parentPathId =
        parentNode.parent_path_id && String(parentNode.parent_path_id).length
          ? `${parentNode.parent_path_id}_${parentNode.id}`
          : String(parentNode.id);

      // 顺序下标: 取子级同级中最大的 order_index + 1；若没有子级则为 0
      const children = parentNode.children;
      const orderIndex = this.getMaxOrderIndexWithin(children) + 1;

      parentNode.children.push(
        this.createNode(
          specIndex,
          orderIndex,
          parentNode.id,
          parentPathId,
          curLevel + 1,
          children.length
        )
      );
      // this.setSortForSiblings(children); //重新排序同级的 sort
    },
    // 工具：创建新节点 specIndex:规格组Index; orderIndex:当前层级中的顺序下标; level:节点层级;
    // parentId:父节点id; parentPathId: “从顶级到父级”的上级id用“_”拼接; sortIndex:排序;
    createNode(
      specIndex,
      orderIndex,
      parentId,
      parentPathId,
      level = 1,
      sortIndex = 0
    ) {
      const spec = this.goodsSpecs[specIndex]; //规格组
      const valueId =
        level == 1
          ? `SV${orderIndex}&${spec.id}` //SV当前索引(顶级节点); SC:specid
          : `${parentId}&CH${orderIndex}`; //CH当前索引(子级节点);

      const node = {
        id: valueId, //id
        parent_id: parentId, //父节点id
        parent_path_id: parentPathId, //“从顶级到父级”的上级id,用“_”拼接
        specid: spec.id, //规格组id
        title: "", //标题
        level, //节点层级
        order_index: orderIndex, // 顺序下标
        sort: sortIndex,
        // children: [],
      };
      if (level === 1) node.productType = 5; // 只在顶级时添加模型类型
      return node;
    },
    // 工具：取同级中“最大的 order_index”
    getMaxOrderIndexWithin(list) {
      if (!Array.isArray(list) || list.length === 0) return -1;
      return Math.max(...list.map((n) => Number(n?.order_index ?? -1)));
    },
    // 工具：重排同级的 sort，保持 0..length-1
    setSortForSiblings(list) {
      (list || []).forEach((n, i) => this.$set(n, "sort", i));
    },
    // 改变模型类型,生成规格组合
    onChangeProductType(specValue) {
      // 仅防御：保证是顶级节点
      if (!specValue || specValue.level !== 1) return;
      // 生成规格组合
      this.generateCombinations(false);
    },
    // 检查规格名  spec:规格组; specIndex:规格组Index
    checkSpecification(spec, specIndex) {
      // 判断是否为第一次输入
      const isInit = !spec?.initialTitle?.trim();
      const isEmpty = !spec?.title?.trim(); //规格名为空
      // 初始化时记录原始值
      if (isInit && spec?.title?.trim()) {
        spec.initialTitle = spec.title;
      }
      if (isEmpty && spec.initialTitle) {
        this.$message.warning("商品规格名不能为空");
      }
      this.checkDuplicateSpecTitle(spec); // 检查当前规格组名称是否有重复
    },
    // 检查规格值并生成规格组合
    // specIndex:规格组Index; valueItem:规格值; valueIndex:规格值Index; list:当前的规格值List(同级)
    checkAndGenerate(specIndex, valueItem, valueIndex, list) {
      const spec = this.goodsSpecs[specIndex];
      // 如果商品规格名为空，显示警告并返回
      if (!spec?.title?.trim()) {
        this.$message.warning("商品规格名不能为空");
        // 清空当前输入的规格值
        valueItem.title = "";
        return;
      }
      // 判断是否为第一次输入
      const isInit = !valueItem?.initialTitle?.trim();
      const isEmpty = !valueItem?.title?.trim(); //规格值为空
      // 初始化时记录原始值
      if (isInit && valueItem?.title?.trim()) {
        valueItem.initialTitle = valueItem.title;
      }
      // 如果规格值为空或重复，不生成组合
      if (isEmpty || this.checkDuplicateValues(list)) {
        // 如果是清空操作且已生成组合，则移除组合
        if (isEmpty && valueItem.initialTitle) {
          // 重新生成组合
          this.generateCombinations(false);
        }
        return;
      }
      // 重新生成组合
      if (list.length === 1) {
        // 添加第一个子级
        // console.log("添加第一个子级", valueItem.parent_path_id);
        this.generateCombinations(false, valueItem.parent_path_id);
      } else {
        this.generateCombinations(false); // ?list.length === 1
      }
    },
    // 排列组合并生成规格详细信息 isNewSpecGroup:重新生成组合; parentPathId:“从顶级到父级”的上级id,用“_”拼接
    generateCombinations(isNewSpecGroup = true, parentPathId = "") {
      // 没有规格组
      if (!Array.isArray(this.goodsSpecs) || this.goodsSpecs.length === 0) {
        this.specDetails = [];
        return;
      }
      if (isNewSpecGroup) {
        this.specDetails = [];
      }

      // 1) 遍历每个规格组：把树形 spec_item 拍平为“有效叶子值”
      const perSpecValues = this.goodsSpecs.map((spec) =>
        this.flattenSpecLeafValues(spec.spec_item)
      );

      // 任一规格组没有有效叶子，则无法生成组合
      // if (perSpecValues.some((arr) => arr.length === 0)) {
      //   this.specDetails = [];
      //   this.$message.error("请确保每个规格组至少有一个有效的规格值");
      //   return;
      // }

      // 2) 生成排列组合 笛卡尔积（每项是：[{id,title,label}, {id,title,label}, ...]）
      const combinations = this.cartesianProduct(...perSpecValues);
      // console.log("perSpecValues", perSpecValues);
      // console.log("combinations", combinations);

      // 3) 生成/更新 规格组合详细信息
      combinations.forEach((combination) => {
        const comTitle = combination.map((it) => it.label).join(" / ");
        const title = combination.map((it) => it.title).join("+");
        const comSpecs = combination.map((it) => it.id).join("_");

        // 关键：按“顶级规格值的 productType”决定本组合的 modelTypes
        const productTypeToUse =
          (combination[0] && combination[0].productType) != null
            ? combination[0].productType
            : 5;

        // 生成 modelTypes 根据当前 productType 配置
        const modelTypes = this.generateModelTypes(productTypeToUse, comTitle);
        // 判断是否已存在组合 1.组合id相同 2.添加第一个子级时，只需更新上一级组合id等数据
        const existDetail = this.specDetails.find((detail) => {
          const sid = String(detail.spec_item_id);
          // if (parentPathId) {
          //   console.log(
          //     "判断是否已存在组合",
          //     sid,
          //     parentPathId,
          //     sid === parentPathId,
          //     comSpecs,
          //     comSpecs.includes(parentPathId)
          //   );
          // }
          return (
            sid === comSpecs ||
            (parentPathId &&
              sid === parentPathId &&
              comSpecs.includes(parentPathId))
          );
        });

        // 尺寸解析（单规格组时才尝试），沿用你原逻辑
        let s_length = "";
        let s_width = "";
        let s_height = "";
        if (this.goodsSpecs.length === 1) {
          ({ s_length, s_width, s_height } =
            this.parseSizeToDimensions(comTitle));
        }

        if (!existDetail) {
          // 如果组合不存在于 specDetails 中，则添加
          this.specDetails.push({
            activeTab: "0",
            spec_item_id: comSpecs, // 组合 id（_拼接）
            productType: productTypeToUse, // 模型类型
            modelTypes: modelTypes.map((model) => ({
              ...model,
              option: {
                id: `SP${this.specDetails.length + 1}_${model.modelType}`,
                title: title, // 保持与你原逻辑一致（叶子标题拼接）
                combination: comTitle, // 展示用（带路径）
                specs: comSpecs,
                // market_price: "", // 商品原价
                product_price: "", // 零售价格
                // cost_price: "", // 成本价格
                singleType:
                  productTypeToUse == 2 || productTypeToUse == 3 ? 1 : null, //1:单人位; 2:双人位 (双模型、四模型才有)
                product_model: "", // 商品型号
                volume: "0.000", // 包装体积
                structure: "", // 材质说明
                length: s_length, // 商品 长
                width: s_width, // 商品 宽
                height: s_height, // 商品 高
                // weight: 0, // 商品重量
                package_number: 1, // 包装件数
                // package_option: [{ length: "", width: "", height: "" }], // 包装尺寸
                // d3model: "", // 弃用
                // d3modelName: "", // 弃用
                d3Max: "", // 3DMax模型
                d3MaxName: "", // 3DMax模型文件名称
                d3MaxUrl: "", // 3DMax模型url(用于渲染3D模型)
                cad_plan_model: "", // CAD模型
                cad_plan_modelName: "", // CAD模型文件名称
                thumb: "", //图片
                thumbName: "", //图片名称
                d3model_url: "", // DIY模型obj文件的url
                model_param: [], //3D模型部件参数
                install_guide: { isPdf: 0, data: [] }, // 安装指南
                wiring_diagram: [], // 走线图
              },
            })),
          });
        } else {
          // 如果组合已存在，更新组合的标题和组合部分
          // existDetail.modelTypes.forEach((model) => {
          //   const same = modelTypes.find((m) => m.modelType == model.modelType);
          //   if (same) model.title = same.title;
          //   model.option.title = title;
          //   model.option.combination = comTitle;
          //   model.option.specs = comSpecs;
          // });
          //添加第一个子级
          if (parentPathId) {
            existDetail.spec_item_id = comSpecs;
          }

          //////
          // console.log("组合已存在");
          existDetail.productType = productTypeToUse; // 写回 productType
          // 以新定义为基准，尽量复用旧的同 modelType 项（保留用户填写的 option）
          const nextModelTypes = modelTypes.map((model) => {
            const old = existDetail.modelTypes.find(
              (m) => m.modelType === model.modelType
            );
            if (old) {
              // 复用旧项，更新组合的标题和组合部分
              // console.log("复用旧项");
              old.title = model.title;
              old.option.title = title;
              old.option.combination = comTitle;
              old.option.specs = comSpecs;
              // 人位数: 1:单人位; 2:双人位 (双模型、四模型才有)
              if (
                (old.option.singleType == null || old.option.singleType == 0) &&
                (productTypeToUse == 2 || productTypeToUse == 3)
              ) {
                old.option.singleType = 1;
              } else if (!(productTypeToUse == 2 || productTypeToUse == 3)) {
                old.option.singleType = null;
              }
              return old;
            }
            // 新增该类型
            // console.log("新增该类型");
            return {
              ...model,
              option: {
                id: `SP${this.specDetails.length + 1}_${model.modelType}`,
                title: title, // 保持与你原逻辑一致（叶子标题拼接）
                combination: comTitle, // 展示用（带路径）
                specs: comSpecs,
                product_price: "", // 零售价格
                singleType:
                  productTypeToUse == 2 || productTypeToUse == 3 ? 1 : null, //1:单人位; 2:双人位 (双模型、四模型才有)
                product_model: "", // 商品型号
                volume: "0.000", // 包装体积
                structure: "", // 材质说明
                length: s_length, // 商品 长
                width: s_width, // 商品 宽
                height: s_height, // 商品 高
                package_number: 1, // 包装件数
                d3Max: "", // 3DMax模型
                d3MaxName: "", // 3DMax模型文件名称
                d3MaxUrl: "", // 3DMax模型url(用于渲染3D模型)
                cad_plan_model: "", // CAD模型
                cad_plan_modelName: "", // CAD模型文件名称
                thumb: "", //图片
                thumbName: "", //图片名称
                d3model_url: "", // DIY模型obj文件的url
                model_param: [], //3D模型部件参数
              },
            };
          });
          // 丢弃旧的“已不在目标定义中的类型”，并替换
          existDetail.modelTypes = nextModelTypes;
        }
      });

      setTimeout(() => {
        // 排序
        this.specDetails.sort((a, b) =>
          String(a.spec_item_id).localeCompare(String(b.spec_item_id), "zh", {
            numeric: true,
          })
        );
        console.log("goodsSpecs", this.goodsSpecs);
        console.log("specDetails", this.specDetails);
      }, 500);

      if (this.specDetails.length === 0) {
        this.$message.error("请添加规格值以生成组合");
      }
    },
    // 辅助：把一个规格组的树形 spec_item 拍平成“叶子值”数组
    // 遍历同级时按 sort、其次 order_index 排序，确保组合顺序与 UI 一致
    // nodes:当前节点的数据; ancestors:;
    // 返回形如：[{ id, title, label }]；
    // - title：叶子节点自身标题（用于计算，如“尺寸”解析）
    // - label：从顶级到叶子的路径展示，如“红色 / 深红”
    // - productType: 模型类型
    flattenSpecLeafValues(nodes, ancestors = []) {
      const res = [];
      // 按 sort、其次 order_index 排序 // 没有用（后面在组合后进行了重新排序）
      const arr = Array.isArray(nodes)
        ? nodes.slice().sort((a, b) => {
            const sa = a?.sort ?? 0,
              sb = b?.sort ?? 0;
            if (sa !== sb) return sa - sb;
            const oa = a?.order_index ?? 0,
              ob = b?.order_index ?? 0;
            return oa - ob;
          })
        : [];

      arr.forEach((node) => {
        const title = String(node?.title || "").trim(); // 当前节点的标题
        if (!title) return; // 仅收集“有标题”的叶子，避免空值参与组合

        // 判断是否有子节点
        if (Array.isArray(node.children) && node.children.length > 0) {
          // 有子节点，继续递归子节点，传递父节点node
          res.push(
            ...this.flattenSpecLeafValues(node.children, [...ancestors, node]) // 将 node 都传递
          );
        } else {
          // console.log("node", node);
          // console.log("ancestors", ancestors);

          // 递归拼接 id 路径（用 _ 拼接）
          const idPath = [...ancestors.map((item) => item.id), node.id].join(
            "_"
          );
          const label = [
            ...ancestors.map((item) => item.title),
            node.title,
          ].join(" / ");

          // 模型类型
          let productType = 5;
          if (ancestors.length === 0) {
            productType = node.productType;
          } else {
            // 找到这一条路径的顶级节点（level===1 的第一个）
            const top = ancestors.find((a) => (a.level || 1) === 1) || {};
            if (top.productType != null) productType = top.productType;
          }

          res.push({
            id: idPath, // 使用路径拼接的 id（唯一标识符）
            title: node.title, // 叶子自身标题（给 size 解析等逻辑用）
            label: label, // 使用 title 拼接的展示路径
            productType: productType, // 模型类型
          });
        }
      });

      return res;
    },
    // 生成排列组合
    cartesianProduct(...arrays) {
      return arrays.reduce(
        (acc, curr) => {
          return acc.flatMap((d) => {
            return curr.map((e) => {
              // 保持组合为对象数组格式
              return [...(Array.isArray(d) ? d : [d]), e];
            });
          });
        },
        [[]]
      );
      // return arrays.reduce((acc, curr) => acc.flatMap(d => curr.map(e => [d, e].flat())));
    },
    // 辅助函数：根据模型类型生成 modelTypes
    // productType,modelType：模型类型 0:独立位(完整位)或十字型; 1:首位或T字型; 2:延伸位或F型; 3:尾位;
    generateModelTypes(productType, title) {
      const modelTypesConfig = {
        // 1: [{ modelType: 0, title: `${title}（完整位）` }],
        1: [{ modelType: 0, title: `${title}` }],
        2: [
          { modelType: 0, title: `${title}（独立位）` },
          { modelType: 2, title: `${title}（延伸位）` },
        ],
        3: [
          { modelType: 0, title: `${title}（独立位）` },
          { modelType: 1, title: `${title}（首位）` },
          { modelType: 2, title: `${title}（延伸位）` },
          { modelType: 3, title: `${title}（尾位）` },
        ],
        4: [
          { modelType: 0, title: `${title}（十字型）` },
          { modelType: 1, title: `${title}（T字型）` },
          { modelType: 2, title: `${title}（L型）` },
          { modelType: 3, title: `${title}（T字型-1）` },
          { modelType: 4, title: `${title}（L型-1）` },
        ],
        5: [{ modelType: 0, title: `${title}` }], //自由组合
      };

      // 获取当前 productType 的 modelTypes 配置，如果没有匹配项则返回空数组
      // const productType = this.form.goods.productType;
      const modelTypes = modelTypesConfig[productType] || [];

      // 初始化每个 modelType 的 option 对象
      return modelTypes.map((model) => ({
        ...model,
      }));
    },
    // 检查当前规格组名称中是否有重复 specItem:规格组
    checkDuplicateSpecTitle(specItem) {
      if (!specItem?.title?.trim()) return false;
      const duplicateCount = this.goodsSpecs.filter(
        (spec) => spec.title.trim() === specItem.title.trim()
      ).length;
      const isDuplicate = duplicateCount > 1;
      if (isDuplicate) {
        specItem.title = specItem.title + "_1";
        this.$message.warning(this.lang.goods.error_add_same_spec);
      }
      return isDuplicate;
    },
    // 检查当前规格组中是否有重复的规格值  list:当前的规格值List(同级)
    checkDuplicateValues(list) {
      const values = list.map((item) => item.title.trim()); // 获取当前规格值列表
      const uniqueValues = new Set(values);

      if (uniqueValues.size !== values.length) {
        this.$message.warning(this.lang.goods.error_add_same_spec_value);

        // 找到最后一个重复的规格值并清空其值
        for (let i = values.length - 1; i >= 0; i--) {
          // 通过 indexOf 找到第一个出现的重复项，若当前项的 index 不同于第一个出现的索引，表示重复
          if (values.indexOf(values[i]) !== i) {
            // 查找重复项
            list[i].title = ""; // 清空重复值的 title
            break;
          }
        }
        return true;
      }

      return false;
    },
    // 移除规格组并更新规格组合   spec:规格组; specIndex:规格组Index;
    removeSpecification(spec, specIndex) {
      // 移除规格组
      this.goodsSpecs.splice(specIndex, 1);
      // 检查是否有生成的规格组合明细
      const hasGeneratedCombinations = this.specDetails.some((detail) =>
        detail.spec_item_id.includes(spec.id)
      );
      // 如果已有规格组合明细，则重新生成规格组合
      if (hasGeneratedCombinations) {
        this.generateCombinations();
      }
    },
    // 移除规格值 siblings:当前的同级规格值数组 index:规格值索引;
    removeSpecificationValue(siblings, index) {
      this.$confirm("确定要删除此规格值吗？删除后无法恢复。", "提示", {
        type: "warning",
      })
        .then(() => {
          const node = siblings[index];
          const valueId = node.id; //规格值Id
          const parentPathId = node.parent_path_id; //从顶级到父级”的上级id
          // 移除规格值
          siblings.splice(index, 1);

          this.setSortForSiblings(siblings); //重新排序同级的 sort

          // 移除与该规格值相关的组合
          this.removeCombinationsForSpecValue(
            valueId,
            siblings.length,
            parentPathId
          );

          // 移除规格值，处理选中索引和id (仅当影响当前选中时，才联动处理)
          this.updateSelectionAfterValueRemoval(siblings, index, valueId);

          console.log("goodsSpecs", this.goodsSpecs);
          console.log("specDetails", this.specDetails);
        })
        .catch(() => {});
    },
    // 移除与某个规格值相关的组合  valueId:规格值Id; siblingLen:同级数据条数; parentPathId:从顶级到父级”的上级id,用“_”拼接
    removeCombinationsForSpecValue(valueId, siblingLen, parentPathId) {
      // 移除相关的组合
      this.specDetails = this.specDetails.filter((detail) => {
        const specsArray = String(detail.spec_item_id).split("_");
        return !specsArray.includes(String(valueId)); // 如果包含 valueId，则移除
      });
      // 移除同级最后一个规格
      if (siblingLen == 0) {
        // 如果是同级最后一个规格值被移除，更新规格组合:添加新的组合(添加第一个子级)
        this.generateCombinations(false, parentPathId);
      }
    },
    // 移除某个规格值后，联动处理选中
    updateSelectionAfterValueRemoval(siblings, index, removedId) {
      // // 1) 不影响：被删节点不在当前选中路径中 → 直接返回 // 删除选中项前面的，索引必然会变
      // if (!this.isCurrentSelectionAffectedBy(removedId)) return;

      // 2) 受影响：计算一个替代选中的 idPath
      const fallbackIdPath = this.getFallbackIdPathAfterRemoval(
        siblings,
        index
      );

      // 3) 应用或清空
      if (fallbackIdPath) {
        this.setSelectionByIdPath(fallbackIdPath);
      } else {
        // 没有任何可选项：清空
        this.selectedPathIds = [];
        this.specDetailSelectedIndex = -1;
        this.selectedTopIndex = 0;
      }
    },
    // 辅助函数：被删节点是否影响当前选中（判断是否在当前选中路径中）
    isCurrentSelectionAffectedBy(removedId) {
      const curPath = (this.selectedPathIds || []).map(String);
      return curPath.includes(String(removedId));
    },
    // 辅助函数：计算“就近替代选中”的 idPath：先尝试同级，就近取同位置 / 最后一个；否则全局取第一个叶子
    getFallbackIdPathAfterRemoval(siblings, removedIndex) {
      // A) 同级还有元素：取同位置（或最后一个）节点的“第一个叶子”
      if (Array.isArray(siblings) && siblings.length > 0) {
        const newIdx = Math.min(removedIndex, siblings.length - 1);
        const candidate = siblings[newIdx];
        const leafPath = this.getFirstLeafIdPathFromNode(candidate);
        if (leafPath) return leafPath;
      }
      // B) 同级空了：全局找“第一个叶子”
      return this.findFirstAvailableLeafIdPath();
    },
    // 辅助函数：从任意节点取“第一个叶子”的 idPath（用 parent_path_id + '_' + id；若自身是叶子则返回自身路径）
    getFirstLeafIdPathFromNode(node) {
      if (!node) return "";
      let cur = node;
      while (Array.isArray(cur.children) && cur.children.length > 0) {
        cur = cur.children[0];
      }
      const base = String(cur.parent_path_id || "");
      return base ? `${base}_${cur.id}` : String(cur.id);
    },
    // 辅助函数：全局找到“第一个可用叶子”的 idPath
    findFirstAvailableLeafIdPath() {
      for (const spec of this.goodsSpecs) {
        const list = (spec && spec.spec_item) || [];
        for (const item of list) {
          const idPath = this.getFirstLeafIdPathFromNode(item);
          if (idPath) return idPath;
        }
      }
      return "";
    },
    // 辅助函数：根据 idPath（如 "SV0&SC0_SV0&SC0&CH0"）统一更新选中
    setSelectionByIdPath(idPath) {
      const pathArr = String(idPath || "")
        .split("_")
        .filter(Boolean);
      this.selectedPathIds = pathArr; //从顶到叶的 id 数组

      // 当前 specDetails 的下标
      this.specDetailSelectedIndex = this.specDetails.findIndex(
        (d) => String(d.spec_item_id) === String(idPath)
      );

      // 选中的顶级规格值索引
      const topId = pathArr[0] || "";
      this.selectedTopIndex = this.findTopIndexByTopId(topId);
    },
    // 辅助函数：根据顶级 id 求其 index（没找到则返回 0）
    findTopIndexByTopId(topId) {
      const idStr = String(topId || "");
      for (const spec of this.goodsSpecs) {
        const list = (spec && spec.spec_item) || [];
        const i = list.findIndex((n) => String(n.id) === idStr);
        if (i !== -1) return i;
      }
      return 0;
    },

    // 检查是否禁用添加规格值按钮  specIndex:规格组Index;
    isAddSpecValueDisabled(specIndex) {
      const checkTitle = (items) => {
        // 遍历每个节点，检查标题是否为空
        return items.some((item) => {
          if (!item.title.trim()) {
            return true; // 如果有空标题，返回 true
          }
          // 如果有子节点，递归检查子节点
          if (item.children && item.children.length > 0) {
            return checkTitle(item.children); // 递归检查子节点
          }
          return false;
        });
      };

      // 检查当前规格组的所有规格值（包括子节点）
      return checkTitle(this.goodsSpecs[specIndex].spec_item);
    },
    // 确认删除规格组  spec:规格组; specIndex:规格组Index;
    confirmRemoveSpecification(spec, specIndex) {
      this.$confirm("确定要删除此规格组吗？删除后无法恢复。", "删除确认", {
        confirmButtonText: "确定",
        cancelButtonText: "取消",
        type: "warning",
      })
        .then(() => {
          this.removeSpecification(spec, specIndex); // 执行删除规格组操作
        })
        .catch(() => {
          this.$message.info("已取消删除");
        });
    },
    // 编辑时 初始化规格组合
    initSpecifications(options, productType) {
      // 规格组
      this.goodsSpecs.push(
        ...options.specs.map((spec) => ({
          ...spec,
          initialTitle: spec.title, // 为规格组添加 initialTitle 属性
          // 这里递归处理规格值
          spec_item: this.deepSpecitemAddInitialTitle(
            spec.spec_item || [],
            productType
          ),
        }))
      );
      //商品规格详细信息
      this.specDetails = options.option.map((opt) => {
        opt.activeTab = "0";
        // 补 productType（仅当未设置时）
        if (opt.productType === undefined || opt.productType === null) {
          opt.productType = productType;
        }
        // const combination = this.getCombinationFromSpecs(opt.spec_item_id);
        // 遍历 modelTypes 更新需要的字段
        const updatedModelTypes = opt.modelTypes.map((model) => ({
          ...model,
          // title: this.getModelTypeName(model.modelType)
          //   ? `${combination}（${this.getModelTypeName(model.modelType)}）`
          //   : combination, // 动态生成标题
          option: {
            ...model.option,
            combination: model.option.title, //combination
            d3MaxName: !model.option.d3MaxName
              ? this.extractFileName(model.option.d3MaxUrl)
              : model.option.d3MaxName, // 提取 文件名
            cad_plan_modelName: !model.option.cad_plan_modelName
              ? this.extractFileName(model.option.cad_plan_model)
              : model.option.cad_plan_modelName, // 提取 cad_plan_modelName
            thumbName: !model.option.thumbName
              ? this.extractFileName(model.option.thumb)
              : model.option.thumbName, // 提取 thumbName
          },
        }));
        // 返回更新后的 specDetail 项
        return {
          ...opt,
          modelTypes: updatedModelTypes,
        };
      });

      this.setSpecDetailSelectedIndexByTop(); // 根据默认选中的顶级规格值索引，联动显示正确的规格组合
      console.log("======goodsSpecs======", this.goodsSpecs);
      console.log("======specDetails======", this.specDetails);
    },
    // 辅助函数：规格值,深度为任意层的递归补充 initialTitle、parent_path_id、order_index
    // ancestorsIds 内部递归使用：从顶级到父级的 id 数组（不含当前节点）
    deepSpecitemAddInitialTitle(items, productType, ancestorsIds = []) {
      if (!Array.isArray(items)) return items;

      return items.map((node, index) => {
        // 顶→父级id，用“_”拼接
        const parentPathId = ancestorsIds.length
          ? ancestorsIds.map(String).join("_")
          : "";

        const next = {
          ...node,
          initialTitle: node.title,
          parent_path_id: parentPathId, //“从顶级到父级”的上级id,用“_”拼接
          // order_index: index,
          // sort: index,
          order_index:
            typeof node.order_index === "number" ? node.order_index : index,
          sort: typeof node.sort === "number" ? node.sort : index,
        };
        // 顶级补 productType（仅当未设置时）
        if (
          (node.level || 1) === 1 &&
          (node.productType === undefined || node.productType === null)
        ) {
          next.productType = productType;
        }

        // 子级递归
        if (Array.isArray(node.children) && node.children.length) {
          next.children = this.deepSpecitemAddInitialTitle(
            node.children,
            productType,
            [...ancestorsIds, node.id] // 新增：向下传递路径
          );
        }

        return next;
      });
    },
    // 辅助函数：根据规格ID获取规格组合的标题
    getCombinationFromSpecs(specs) {
      return String(specs)
        .split("_")
        .map((specId) => {
          for (let spec of this.goodsSpecs) {
            let item = spec.spec_item.find((item) => item.id == specId);
            if (item) return item.title;
          }
          return "";
        })
        .join(" / ");
    },
    // 辅助函数：编辑时，根据选中的顶级规格值索引 selectedTopIndex（默认0）,联动到正确的规格组合 tabs：
    setSpecDetailSelectedIndexByTop() {
      // 没有规格组或组合则不处理（防御）
      if (!Array.isArray(this.goodsSpecs) || this.goodsSpecs.length === 0)
        return;
      if (!Array.isArray(this.specDetails) || this.specDetails.length === 0)
        return;

      // 仅以第一个规格组的顶级行为驱动（与现有 buildIdIndex/联动逻辑一致）
      const topList = this.goodsSpecs[0]?.spec_item || [];
      if (!Array.isArray(topList) || topList.length === 0) return;

      // 保障 selectedTopIndex 合法，默认 0
      const safeIndex = Math.max(
        0,
        Math.min(Number(this.selectedTopIndex) || 0, topList.length - 1)
      );

      const node = topList[safeIndex]; // 顶级节点
      this.handleSpecValueSelect(node, safeIndex, 1); // 联动到对应的规格组合
    },
    // 辅助函数：获取模型类型名称
    // modelType：模型类型 0:独立位(完整位)或十字型; 1:首位或T字型; 2:延伸位或F型; 3:尾位;
    getModelTypeName(modelType) {
      const modelTypeNames = {
        // 1: { 0: "完整位" }, // 单模型
        1: { 0: "" }, // 单模型
        2: { 0: "独立位", 2: "延伸位" }, // 双模型
        3: { 0: "独立位", 1: "首位", 2: "延伸位", 3: "尾位" }, // 四模型
        4: { 0: "十字型", 1: "T字型", 2: "L型", 3: "T字型-1", 4: "L型-1" }, // 屏风
        5: { 0: "" }, // 自由组合
      };
      const productType = this.form.goods.productType;
      return modelTypeNames[productType]?.[modelType] || ""; // 若找不到匹配则返回空字符串
    },
    // 辅助函数：提取文件名
    extractFileName(filePath) {
      if (!filePath) return "";
      const parts = filePath.split("/");
      return parts[parts.length - 1];
    },
    // 辅助函数：提取长宽高
    parseSizeToDimensions(title) {
      // 校验 title 是否有效
      if (!title) {
        return { s_length: "", s_width: "", s_height: "" };
      }

      // 使用正则表达式匹配格式如 "1400*600*750"
      const sizeRegex = /(\d+)\s*\*\s*(\d+)\s*\*\s*(\d+)/;
      const match = title.match(sizeRegex);

      // 如果找到了匹配的尺寸格式
      if (match) {
        const [_, s_length, s_width, s_height] = match;

        // 校验是否为数值
        if (
          isNaN(Number(s_length)) ||
          isNaN(Number(s_width)) ||
          isNaN(Number(s_height))
        ) {
          console.error("规格值必须为数值:", { s_length, s_width, s_height });
          return { s_length: "", s_width: "", s_height: "" };
        }

        return { s_length, s_width, s_height };
      }

      // 如果没有找到有效尺寸，返回默认值
      return { s_length: "", s_width: "", s_height: "" };
    },
    // 辅助函数：
    truncate(value, maxLength = 15) {
      return value.length > maxLength
        ? value.slice(0, maxLength) + "..."
        : value;
    },
    // 更新包装件数并动态调整包装尺寸  index:specDetails的Index; tabIndex:specDetail.modelTypes的Index; newPackageNumber:包装件数;
    updatePackageNumber(index, tabIndex, newPackageNumber) {
      // 获取当前option
      const option = this.specDetails[index]?.modelTypes[tabIndex]?.option;
      // 解析包装件数
      const packageNumber = parseInt(newPackageNumber, 10);
      // 确保包装件数是有效的数字
      if (isNaN(packageNumber) || packageNumber < 1) {
        option.package_number = 1; // 默认为1
      } else {
        option.package_number = packageNumber; // 更新包装件数
      }
      // 调整 package_option 的长度
      // option.package_option = Array.from(
      //   { length: option.package_number },
      //   () => ({
      //     length: "",
      //     width: "",
      //     height: "",
      //   })
      // );
    },
    // 更新人数位(双模型、四模型：首位,延生位,尾位的人数位要与独立位保持一致) index:specDetails的Index; tabIndex:specDetail.modelTypes的Index; newSingleType:人数位;
    updateSingleType(index, tabIndex, newSingleType) {
      // 获取当前option
      const specDetail = this.specDetails[index];
      specDetail?.modelTypes.forEach((detail) => {
        if ([1, 2, 3].includes(detail.modelType)) {
          detail.option.singleType = newSingleType;
        }
      });
    },
    // 通用函数：计算包装体积  index:specDetails的Index; tabIndex:specDetail.modelTypes的Index;
    calculatePackageVolume(index, tabIndex) {
      // 获取当前option
      const option = this.specDetails[index]?.modelTypes[tabIndex]?.option;
      if (!option.package_option || !Array.isArray(option.package_option)) {
        return 0; // 如果 package_option 不存在或不是数组，返回 0
      }

      option.volume = option.package_option.reduce((totalVolume, pkg) => {
        const length = parseFloat(pkg.length) / 1000 || 0;
        const width = parseFloat(pkg.width) / 1000 || 0;
        const height = parseFloat(pkg.height) / 1000 || 0;

        // 当前包装体积 = 长 * 宽 * 高
        const volume = length * width * height;

        return totalVolume + volume; // 累加所有包装的体积
      }, 0); // 初始值为 0
    },
    // 切换规格值显示对应的规格组合
    // 选中规格值时，联动显示对应的规格详情  node:当前节点; index:当前选中的索引
    handleSpecValueSelect(node, index, depth) {
      // 设置选中的顶级索引
      if (depth === 1) {
        this.selectedTopIndex = index;
      }

      if (!node) return;

      // 1) 先尝试此节点的精确组合
      const exactPath = this.idPathOfNode(node);
      let idx = this.specDetails.findIndex(
        (d) => String(d.spec_item_id) === exactPath
      );
      let targetNode = node;

      // 2) 若不是叶子或未生成，尝试它的第一个叶子
      if (idx === -1) {
        const leaf = this.findFirstLeafNode(node);
        if (leaf) {
          const leafPath = this.idPathOfNode(leaf);
          const found = this.specDetails.findIndex(
            (d) => String(d.spec_item_id) === leafPath
          );
          if (found !== -1) {
            idx = found;
            targetNode = leaf; // 更新为叶子节点
          }
        }
      }

      // 3) 兜底：找第一条包含该节点 id 的组合
      if (idx === -1) {
        const nid = String(node.id);
        const found = this.specDetails.findIndex((d) =>
          String(d.spec_item_id).includes(nid)
        );
        if (found !== -1) {
          idx = found;
          // 同步 targetNode 为该组合的末级节点（从 spec_item_id 反推出最后一个 id）
          const lastId = String(this.specDetails[found].spec_item_id)
            .split("_")
            .pop();
          // 将 targetNode 设为“同级中 id=lastId 的节点”，找不到就维持原 node
          const idMap = this.buildIdIndex(this.goodsSpecs[0]?.spec_item || []);
          const maybe = idMap && idMap.get(String(lastId));
          if (maybe) targetNode = maybe;
        }
      }

      // 更新 tabs 显示
      if (idx !== -1) this.specDetailSelectedIndex = idx;

      // 把“应选中的节点路径”下发给子组件，让其 selectedIndex 对齐
      this.selectedPathIds = this.nodePathIdsArray(targetNode);
    },
    // 辅助函数：组装该节点的“完整 id 路径”（与 specDetails.spec_item_id 一致的格式）
    idPathOfNode(node) {
      const pid = String(node.parent_path_id || "");
      const nid = String(node.id);
      return pid ? `${pid}_${nid}` : nid;
    },
    // 辅助函数：把节点转成 “从顶到当前” 的 id 数组（用于子组件选中）
    nodePathIdsArray(node) {
      const arr = String(node.parent_path_id || "")
        .split("_")
        .filter(Boolean);
      arr.push(String(node.id));
      return arr;
    },
    // 辅助函数：找到此节点下第一个叶子节点（优先有标题）
    findFirstLeafNode(node) {
      let cur = node;
      while (cur && Array.isArray(cur.children) && cur.children.length) {
        const t = cur.children.find((c) => String(c.title || "").trim());
        cur = t || cur.children[0];
      }
      return cur || node;
    },
    // 辅助函数：构建 id→node 索引（字符串 key）
    buildIdIndex(nodes, map = new Map()) {
      if (!Array.isArray(nodes)) return map;
      nodes.forEach((n) => {
        map.set(String(n.id), n);
        if (Array.isArray(n.children) && n.children.length) {
          this.buildIdIndex(n.children, map);
        }
      });
      return map;
    },
    // 复制设置
    // 打开复制设置弹窗
    openCopySettings() {
      this.copySettingsVisible = true;
      // 打开时同步一次全选/半选状态
      this.syncCheckAllState();
    },
    // 保存复制设置
    saveCopySettings() {
      if (!this.copySelectedFields.length) {
        this.copySelectedFields = this.getDefaultCopyFields();
      }
      window.localStorage.setItem(
        this.storageCopyFieldsKey,
        JSON.stringify(this.copySelectedFields)
      );
      this.$message.success("保存成功！");
      this.copySettingsVisible = false;
    },
    // 默认勾选（除 product_price 之外的所有字段）
    getDefaultCopyFields() {
      return this.copyFieldOptions
        .filter((i) => i.value !== "product_price")
        .map((i) => i.value);
    },
    // 恢复默认
    resetCopySettings() {
      this.copySelectedFields = this.getDefaultCopyFields();
      this.syncCheckAllState();
    },
    onCheckAllChange(val) {
      this.copySelectedFields = val
        ? this.copyFieldOptions.map((i) => i.value)
        : [];
      this.isIndeterminate = false;
    },
    onFieldChange(val) {
      this.syncCheckAllState();
    },
    syncCheckAllState() {
      const total = this.copyFieldOptions.length;
      const checkedCount = this.copySelectedFields.length;
      this.copySetCheckAll = checkedCount === total;
      this.isIndeterminate = checkedCount > 0 && checkedCount < total;
    },
    // 工具：深拷贝
    deepClone(obj) {
      return obj == null ? obj : JSON.parse(JSON.stringify(obj));
    },
    // 工具：支持 File / Blob 的安全深拷贝
    safeClone(val) {
      const isBlob = typeof Blob !== "undefined" && val instanceof Blob;
      const isFile = typeof File !== "undefined" && val instanceof File;
      if (isBlob || isFile) return val; // 不做 JSON 序列化

      if (Array.isArray(val)) return val.map((v) => this.safeClone(v));
      if (val && typeof val === "object") {
        const out = {};
        Object.keys(val).forEach((k) => (out[k] = this.safeClone(val[k])));
        return out;
      }
      return val;
    },
    // 根据选择项构建可复制的数据
    buildCopyDataFromOption(option) {
      const selected = new Set(
        this.copySelectedFields && this.copySelectedFields.length
          ? this.copySelectedFields
          : this.getDefaultCopyFields() // 没设置时，默认不含 product_price
      );

      const simpleFields = [
        "product_price",
        "product_model",
        "volume",
        "structure",
        "length",
        "width",
        "height",
        "package_number",
        "wiring_diagram",
      ];

      const data = {};

      // 1) 简单字段：存在即复制
      simpleFields.forEach((key) => {
        if (selected.has(key) && option[key] !== undefined) {
          data[key] = this.safeClone(option[key]);
        }
      });

      // 2) 特殊字段
      // 安装指南
      if (selected.has("install_guide")) {
        if (option.install_guide !== undefined)
          data.install_guide = this.deepClone(option.install_guide);
        if (option.install_guide_url !== undefined)
          data.install_guide_url = this.safeClone(option.install_guide_url);
      }
      // 白底图
      if (selected.has("thumb")) {
        if (option.thumb !== undefined)
          data.thumb = this.safeClone(option.thumb);
        if (option.thumbName !== undefined)
          data.thumbName = this.safeClone(option.thumbName);
        if (option.thumb_url !== undefined)
          data.thumb_url = this.safeClone(option.thumb_url);
      }
      // 3DMax模型：勾选时同时复制三个字段
      if (selected.has("d3Max")) {
        if (option.d3Max !== undefined)
          data.d3Max = this.safeClone(option.d3Max);
        if (option.d3MaxName !== undefined)
          data.d3MaxName = this.safeClone(option.d3MaxName);
        if (option.d3MaxUrl !== undefined)
          data.d3MaxUrl = this.safeClone(option.d3MaxUrl);
      }
      // cad图纸：勾选时同时复制两个字段
      if (selected.has("cad_plan_model")) {
        if (option.cad_plan_model !== undefined)
          data.cad_plan_model = this.safeClone(option.cad_plan_model);
        if (option.cad_plan_modelName !== undefined)
          data.cad_plan_modelName = this.safeClone(option.cad_plan_modelName);
      }

      // 3) DIY模型
      if (selected.has("d3model")) {
        // DIY模型文件
        if (option.d3model_url !== undefined)
          data.d3model_url = this.safeClone(option.d3model_url); // diy模型的url
        if (option.d3ModelUrl_weld !== undefined)
          data.d3ModelUrl_weld = this.safeClone(option.d3ModelUrl_weld);
        if (option.d3ModelUrl_ori !== undefined)
          data.d3ModelUrl_ori = this.safeClone(option.d3ModelUrl_ori);
        if (option.d3ModelUrl_ori_file !== undefined)
          data.d3ModelUrl_ori_file = this.safeClone(option.d3ModelUrl_ori_file); // File
        if (option.d3ModelUrl_buffer !== undefined)
          data.d3ModelUrl_buffer = this.safeClone(option.d3ModelUrl_buffer); // Blob
        // 减面模型
        if (option.modelSlim !== undefined)
          data.modelSlim = this.deepClone(option.modelSlim); // 模型减面数据
        if (option.thumb3dModelUrl !== undefined)
          data.thumb3dModelUrl = this.safeClone(option.thumb3dModelUrl); // 减面模型的url
        if (option.thumb3dModelUrl_buffer !== undefined)
          data.thumb3dModelUrl_buffer = this.safeClone(
            option.thumb3dModelUrl_buffer
          ); // 减面模型Blob数据
      }
      if (selected.has("model_param")) {
        // DIY模型部件 model_param[]：去掉 id 再复制
        const src = Array.isArray(option.model_param) ? option.model_param : [];
        data.model_param = src.map((part) => {
          // 只去掉顶层 id，保留其他字段
          if (part && typeof part === "object") {
            const { id, ...rest } = part; // 去掉 id
            return this.deepClone(rest);
          }
          return part;
        });
      }

      return data;
    },
    // 复制 商品规格数据  index:specDetails的Index; tabIndex:specDetail.modelTypes的Index;
    // option:当前操作的specDetail.modelTypes的detail.option;
    onCopySpec(index, tabIndex, option) {
      this.$set(this.copied, `${index}_${tabIndex}`, true);
      setTimeout(() => {
        this.$set(this.copied, `${index}_${tabIndex}`, false);
      }, 3000);

      // 按设置构建复制数据
      this.copySpecOptionData = this.buildCopyDataFromOption(option);
      // console.log("复制前的数据", option);
      // console.log("复制的数据（按设置）", this.copySpecOptionData);
    },
    // 粘贴 商品规格数据  index:specDetails的Index; tabIndex:specDetail.modelTypes的Index;
    onPasteSpec(index, tabIndex) {
      if (
        !this.copySpecOptionData ||
        Object.keys(this.copySpecOptionData).length === 0
      ) {
        this.$message.error("没有可粘贴的数据，请先复制规格数据");
        return;
      }

      const option = this.specDetails?.[index]?.modelTypes?.[tabIndex]?.option;
      if (!option) {
        this.$message.error("粘贴位置无效");
        return;
      }

      this.$set(this.pasted, `${index}_${tabIndex}`, true);
      setTimeout(() => {
        this.$set(this.pasted, `${index}_${tabIndex}`, false);
      }, 3000);

      // 粘贴时逐键赋值，保持响应式；数组/对象用深拷贝
      Object.keys(this.copySpecOptionData).forEach((key) => {
        const val = this.safeClone(this.copySpecOptionData[key]);
        // 对 model_param 用 $set，确保表单/列表能响应更新
        if (key === "model_param") {
          this.$set(option, "model_param", Array.isArray(val) ? val : []);
        } else {
          this.$set(option, key, val);
        }
      });
    },

    // 清除上传的文件
    //清除上传产品图册
    clearPdf() {
      this.form.goods.e_catalog_pdf = "";
      this.form.goods.pdf_page = [];
      this.form.goods.pdfPath = null;
      this.eCatalogPdfName = "";
    },
    //清除保养手册
    clearMaintenanceDoc() {
      this.form.goods.maintenance_doc = "";
      this.maintenanceDocName = "";
    },
    //清除安装指南
    clearInstallGuide() {
      this.form.goods.install_guide = "";
      this.installGuideName = "";
    },
    // 清除3DMax模型文件
    clear3DMax(detail) {
      detail.d3Max = "";
      detail.d3MaxName = "";
      detail.d3MaxUrl = "";
    },
    // 清除CAD模型文件
    clearCADModel(detail) {
      detail.cad_plan_model = "";
      detail.cad_plan_modelName = "";
    },
    // 清除图片
    clearThumb(detail) {
      detail.thumb = "";
      detail.thumbName = "";
    },

    // 打开批量设置的弹窗
    openBatchSetDialog() {
      this.batchSetDialogVisible = true;
    },
    // 单独批量设置商品规格某个字段
    applySingleBatchSet(field) {
      this.specDetails.forEach((spec) => {
        spec.modelTypes.forEach((detail) => {
          // 处理包装件数的特殊情况
          if (
            field === "package_number" &&
            this.batchSetForm.package_number !== ""
          ) {
            detail.option.package_number = parseInt(
              this.batchSetForm.package_number,
              10
            );
            // 更新 package_option 的数量
            // detail.option.package_option = Array.from(
            //   { length: detail.option.package_number },
            //   () => ({
            //     length: "",
            //     width: "",
            //     height: "",
            //   })
            // );
          } else if (this.batchSetForm[field] !== "") {
            detail.option[field] = this.batchSetForm[field];
          }
        });
      });
      // 弹出成功提示
      this.$message.success(this.lang.goods.batch_set_success_tips);
    },
    // 处理包装件数失去焦点时的逻辑
    handlePackageNumberBlur() {
      // 将包装件数转换为整数
      const packageNumber = parseInt(this.batchSetForm.package_number, 10);
      // 如果包装件数无效或小于1，则重置为1
      if (isNaN(packageNumber) || packageNumber < 1) {
        this.batchSetForm.package_number = 1;
        this.$message.warning(this.lang.goods.batch_set_package_number_tips);
      } else {
        this.batchSetForm.package_number = packageNumber;
      }
    },
    // 通用函数：检查数组是否为空
    isArrayEmpty(arr) {
      return arr === undefined || (Array.isArray(arr) && arr.length === 0);
    },

    // 打开 3D 模型页面
    // index:当前操作的specDetails的Index; tabIndex:当前操作的specDetail.modelTypes的Index; option
    openD3Model(index, tabIndex, option) {
      const specDetail = this.specDetails[index];
      const modelType = specDetail?.modelTypes[tabIndex];
      this.d3ModelData.d3ModelUrl = option.d3model_url;
      this.d3ModelData.specDetailsIndex = index; //当前操作的specDetails的Index
      this.d3ModelData.specDetailTabsIndex = tabIndex;
      this.d3ModelData.spec_item_id = specDetail.spec_item_id;
      this.d3ModelData.modelType = modelType.modelType;
      this.d3ModelData.option_id = option.id;
      this.d3ModelData.model_param = option.model_param;
      this.d3Show = true;
      console.log("========this.d3ModelData=======", this.d3ModelData);
    },
    // 保存 3D 部件参数
    handleD3Save(result) {
      console.log("接收到的 3D 部件数据:", result);
      // 获取当前组合spec
      const specDetail = this.specDetails[result.specDetailsIndex];
      // 获取当前modelType
      const modelType = specDetail?.modelTypes[result.specDetailTabsIndex];
      // 获取当前option
      const option = modelType?.option;
      option.model_param = result.model_param; //赋值给当前规格组合

      // 新增时，缓存第一个的部件参数
      if (this.isAdd && modelType?.modelType == 0) {
        this.d3ModelData.default_param[specDetail.spec_item_id] =
          result.model_param;
      }

      //关闭 3D 模型页面
      this.d3Show = false;
      this.cleard3ModelData(); // 清除数据
    },
    //合并配色并去重 用于页面显示
    mergeD3Colors(default_color, select_color) {
      // 合并 default_color 和 select_color，并通过 Map 去重
      return Array.from(
        new Map(
          [...(default_color ? [default_color] : []), ...select_color].map(
            (color) => [color.id, color]
          ) // 使用 id 作为键
        ).values()
      );
    },
    // 关闭前回调 3D模型页面
    handleD3Close() {
      this.d3Show = false;
      this.cleard3ModelData(); // 清除数据
    },
    // 清除数据
    cleard3ModelData() {
      this.d3ModelData.d3ModelUrl = null;
      this.d3ModelData.specDetailsIndex = "";
      this.d3ModelData.specDetailTabsIndex = "";
      this.d3ModelData.spec_item_id = "";
      this.d3ModelData.modelType = null;
      this.d3ModelData.option_id = "";
      this.d3ModelData.model_param = [];
    },

    //关联商品
    choseRelationGoods() {
      this.goods_show = true;
      this.searchGoods(1);
    },
    //弹窗搜索商品 - 选择关联商品组件
    searchGoods(page) {
      let that = this;
      this.loading = true;

      this.$http
        .post(goods_url, {
          keyword: this.goods_keyword,
          except_supplier: 1,
          page: page,
          goods_id: this.form.goods.id,
        })
        .then(
          (response) => {
            if (response.data.result) {
              let datas = response.data.data.goods;
              this.goods_info.goods_list = datas.data;
              this.goods_info.page_total = datas.total;
              this.goods_info.page_size = datas.per_page;
              this.goods_info.current_page = datas.current_page;
              this.goods_info.real_search_form = this.goods_keyword;
              this.goods_info.goods_list.forEach((item, index) => {
                if (item.title) {
                  item.title = this.escapeHTML(item.title);
                }
                // 是否相互关联绑定属性 (用于页面交互)
                const rel = this.form.goods.goods_relation.find(
                  (r) => r.id === item.id
                );
                if (rel) {
                  this.$set(item, "is_bidirectional", rel.is_relation);
                  this.$set(item, "is_relation", rel.is_relation);
                } else {
                  this.$set(item, "is_relation", -1);
                  //同一个供应商厂家
                  if (item.supp_id == this.form.goods.supp_id) {
                    // 相互关联
                    this.$set(item, "is_bidirectional", 1);
                  } else {
                    this.$set(item, "is_bidirectional", 0);
                  }
                }
              });
              // console.log("goods_list", this.goods_info.goods_list);
            } else {
              this.$message({ message: response.data.msg, type: "error" });
            }
            this.loading = false;
          },
          (response) => {
            this.loading = false;
          }
        );
    },
    // 检查是否关联(暂未使用)
    checkExistRelation(id) {
      return this.form.goods.goods_relation.some((r) => r.id === id);
    },
    // 关联
    chooseGoods(row) {
      // 防重复
      const isExist = this.form.goods.goods_relation.some(
        (r) => r.id === row.id
      );
      if (isExist) {
        this.$message.error("请勿重复选择");
        return;
      }

      this.goods_info.good_names.push(row.title);
      if (row.is_bidirectional == 1) {
        this.form.goods.goods_relation.push({ id: row.id, is_relation: 1 }); //属性is_relation: 1.双向关联 0.单向关联
        this.$set(row, "is_relation", 1); // 排序用：1.双向关联
      } else {
        this.form.goods.goods_relation.push({ id: row.id, is_relation: 0 }); //属性is_relation: 1.双向关联 0.单向关联
        this.$set(row, "is_relation", 0); // 排序用：0.单向关联
      }

      // console.log("goods_relation", this.form.goods.goods_relation);
      // console.log("goods_list", this.goods_info.goods_list);
    },
    // 相互关联
    handleRelationChange(row) {
      const relationList = this.form.goods.goods_relation;
      const idx = relationList.findIndex((r) => r.id === row.id);

      // 已存在
      if (idx !== -1) {
        if (row.is_bidirectional === 1) {
          // 相互关联
          this.$set(relationList[idx], "is_relation", 1); //1.双向关联
          this.$set(row, "is_relation", 1); //1.双向关联
        } else {
          // 取消相互关联
          this.$set(relationList[idx], "is_relation", 0); //0.单向关联
          this.$set(row, "is_relation", 0); //0.单向关联
        }
        // console.log("goods_relation", this.form.goods.goods_relation);
        // console.log("goods_list", this.goods_info.goods_list);

        // 强制更新视图
        this.$forceUpdate();
      }
    },
    // 取消关联
    cancelGoods(row) {
      // 根据商品ID找到在 goods_relation 中的索引
      const index = this.form.goods.goods_relation.findIndex(
        (item) => item.id === row.id
      );

      if (index > -1) {
        // 从已关联商品列表中移除
        this.goods_info.good_names.splice(index, 1);
        this.form.goods.goods_relation.splice(index, 1);
      }

      // this.$set(row, "is_bidirectional", 0); // 页面交互：0.非双向关联
      this.$set(row, "is_relation", -1); //-1.无关联

      // console.log("goods_relation", this.form.goods.goods_relation);
      // console.log("goods_list", this.goods_info.goods_list);
    },
    // 关闭-取消关联
    closeGoods(index) {
      // 获取要移除的商品ID
      const removedId = this.form.goods.goods_relation[index].id;

      // 从已关联商品列表中移除
      this.form.goods.goods_relation.splice(index, 1);
      this.goods_info.good_names.splice(index, 1);

      // 更新商品列表中的关联状态
      const goodsItem = this.goods_info.goods_list.find(
        (item) => item.id === removedId
      );
      if (goodsItem) {
        this.$set(goodsItem, "is_bidirectional", 0);
      }

      // 强制更新视图
      this.$forceUpdate();
    },
    escapeHTML(a) {
      a = "" + a;
      return a
        .replace(/&amp;/g, "&")
        .replace(/&lt;/g, "<")
        .replace(/&gt;/g, ">")
        .replace(/&quot;/g, '"')
        .replace(/&apos;/g, "'");
    },

    // 提交前检查规格组和specDetails是否全部填写
    validateSpecAndDetails() {
      // 遍历所有规格组
      for (const specItem of this.goodsSpecs) {
        // 校验规格组 title 是否为空
        if (!specItem?.title?.trim()) {
          this.$message({ message: "规格组名称不能为空", type: "warning" });
          return false;
        }
        // 校验规格值的 title 是否为空
        const hasEmptyValue = specItem.spec_item.some(
          (item) => !item.title?.trim()
        );
        if (hasEmptyValue) {
          this.$message({ message: "规格值名称不能为空", type: "warning" });
          return false;
        }
      }

      // 遍历specDetails中的每一项
      for (let index = 0; index < this.specDetails.length; index++) {
        const spec = this.specDetails[index];

        for (let tabIndex = 0; tabIndex < spec.modelTypes.length; tabIndex++) {
          const detail = spec.modelTypes[tabIndex];
          const option = detail.option;
          const title = detail.title;
          const comIndex = `${index}_${tabIndex}`;
          const modelType = detail.modelType.toString();
          // 检查每个必填字段
          // console.log("===", option.market_price);
          // if (option.market_price === "") {
          //   this.$message.error(
          //     `${title} 规格组合中市场价未填写，请补充完整！`
          //   );
          //   this.$refs.market_price[index].focus();
          //   return false;
          // } else
          if (!option.product_price) {
            this.$message.error(
              `${title} 规格组合中商品售价未填写，请补充完整！`
            );
            // 切换 Tab 并聚焦到指定的输入框
            this.focusField("product_price", index, spec, comIndex, modelType);
            return false;
          } else if (!option.product_model) {
            this.$message.error(
              `${title} 规格组合中商品型号未填写，请补充完整！`
            );
            this.focusField("product_model", index, spec, comIndex, modelType);
            return false;
          } else if (
            (spec.productType == 2 || spec.productType == 3) &&
            !option.singleType
          ) {
            this.$message.error(
              `${title} 规格组合中人位数未填写，请补充完整！`
            );
            this.focusField("singleType", index, spec, comIndex, modelType);
            return false;
          }
          // else if (!option.cost_price) {
          //   this.$message.error(
          //     `${title} 规格组合中成本价未填写，请补充完整！`
          //   );
          //   this.$refs.cost_price[index].focus();
          //   return false;
          // }
          else if (!option.length) {
            this.$message.error(
              `${title} 规格组合中商品规格长度未填写，请补充完整！`
            );
            this.focusField("length", index, spec, comIndex, modelType);
            return false;
          } else if (!option.width) {
            this.$message.error(
              `${title} 规格组合中商品规格宽度未填写，请补充完整！`
            );
            this.focusField("width", index, spec, comIndex, modelType);
            return false;
          } else if (!option.height) {
            this.$message.error(
              `${title} 规格组合中商品规格高度未填写，请补充完整！`
            );
            this.focusField("height", index, spec, comIndex, modelType);
            return false;
            // } else if (!option.weight) {
            //   this.$message.error(`${title} 规格组合中重量未填写，请补充完整！`);
            //   this.focusField("weight", index, spec, comIndex, modelType);
            //   return false;
          } else if (!option.cad_plan_model) {
            this.$message.error(
              `${title} 规格组合中CAD平面图未上传，请补充完整！`
            );
            this.focusField("cad_plan_model", index, spec, comIndex, modelType);
            return false;
          } else if (!option.thumb) {
            this.$message.error(
              `${title} 规格组合中白底图未上传，请补充完整！`
            );
            this.focusField("option_thumb", index, spec, comIndex, modelType);
            return false;
          } else if (!option.package_number) {
            this.$message.error(
              `${title} 规格组合中包装数量未填写，请补充完整！`
            );
            this.focusField("package_number", index, spec, comIndex, modelType);
            return false;
          } else if (!option.d3MaxUrl) {
            this.$message.error(
              `${title} 规格组合中3DMax模型未上传，请补充完整！`
            );
            this.focusField(
              "option_d3MaxUrl",
              index,
              spec,
              comIndex,
              modelType
            );
            return false;
          }
          // 选了文件状态 && 非第一次编辑状态 && 兼容旧版
          else if (
            !option.d3ModelUrl_ori_file &&
            !option.d3ModelUrl_ori &&
            !option.d3model_url
          ) {
            this.$message.error(
              `${title} 规格组合中DIY编辑器3D模型未上传，请补充完整！`
            );
            this.focusField(
              "option_d3model_url",
              index,
              spec,
              comIndex,
              modelType
            );
            return false;
          } else if (!option.volume) {
            this.$message.error(
              `${title} 规格组合中包装体积未填写，请补充完整！`
            );
            this.focusField("volume", index, spec, comIndex, modelType);
            return false;
          } else if (!option.structure) {
            this.$message.error(
              `${title} 规格组合中材质说明未填写，请补充完整！`
            );
            this.focusField("structure", index, spec, comIndex, modelType);
            return false;
          }

          // 编辑时检查模型同步任务状态
          if (!this.isAdd) {
            const statusInfo = this.getOptionModelStatus(option.id); // 获取模型同步信息
            // 有状态记录，且不是 completed 时，不允许保存
            if (statusInfo && statusInfo.overallStatus !== "completed") {
              let msg = "";
              if (statusInfo.overallStatus === "processing") {
                msg = `${title} 规格组合中 3D 模型正在同步中，请同步完成后再保存！`;
              } else if (statusInfo.overallStatus === "failed") {
                msg = `${title} 规格组合中 3D 模型同步失败，请检查后重新上传或重新发起同步！`;
              } else {
                // pending 之类
                msg = `${title} 规格组合中 3D 模型尚未开始同步，请同步完成后再保存！`;
              }
              this.$message.error(msg);
              this.focusField(
                "option_d3model_url",
                index,
                spec,
                comIndex,
                modelType
              );
              return false;
            }
          }

          // 进一步检查package_option中的每个包装尺寸
          // for (
          //   let optIndex = 0;
          //   optIndex < option.package_option.length;
          //   optIndex++
          // ) {
          //   const opt = option.package_option[optIndex];
          //   const compIndex = `${index}_${tabIndex}_${optIndex}`;
          //   const tip = `${title} 规格组合中的第 ${optIndex + 1} 个包装尺寸`;
          //   if (!opt.length) {
          //     this.$message.error(`${tip} 长度未填写！`);
          //     this.focusField("optlength", spec, compIndex, modelType);
          //     return false;
          //   } else if (!opt.width) {
          //     this.$message.error(`${tip} 宽度未填写！`);
          //     this.focusField("optwidth", spec, compIndex, modelType);
          //     return false;
          //   } else if (!opt.height) {
          //     this.$message.error(`${tip} 高度未填写！`);
          //     this.focusField("optheight", spec, compIndex, modelType);
          //     return false;
          //   }
          // }
        }
      }
      return true; // 所有校验通过
    },
    // 新增：根据 specDetails 的一条记录，选中规格行 & 切到对应组合
    beforeFocusSelectSpecDetail(specDetail, index) {
      // 1) 切换到对应组合
      this.specDetailSelectedIndex = index;

      // 2) 让子组件（规格值行）选中对应的节点路径
      // spec_item_id 是 id 路径，用 "_" 拼接
      this.selectedPathIds = String(specDetail.spec_item_id).split("_");

      // 3) 顶级规格值索引
      const topId = this.selectedPathIds[0] || "";
      let foundIndex = -1;
      for (const spec of this.goodsSpecs) {
        if (Array.isArray(spec.spec_item)) {
          const i = spec.spec_item.findIndex(
            (n) => String(n.id) === String(topId)
          );
          if (i !== -1) {
            foundIndex = i;
            break; // 找到即退出
          }
        }
      }
      this.selectedTopIndex = foundIndex !== -1 ? foundIndex : 0;
    },
    /**
     * 通用聚焦方法
     * @param fieldName 字段名的前缀，例如 "product_price"
     * @param index     specDetails 的索引（用于联动选中）
     * @param spec specDetails信息
     * @param comIndex 索引(组合索引)
     * @param activeTab 当前需要激活的 Tab 值
     */
    focusField(fieldName, index, spec, comIndex, activeTab = null) {
      // 先联动子组件选中并切到对应组合
      this.beforeFocusSelectSpecDetail(spec, index);

      const refName = `${fieldName}_${comIndex}`;

      // 切换 Tab，如果需要
      if (activeTab !== null && spec.activeTab !== activeTab) {
        spec.activeTab = activeTab; // 切换到对应的 Tab
      }

      // 确保 DOM 更新完成后进行操作
      this.$nextTick(() => {
        const fieldRef = this.$refs[refName];

        if (Array.isArray(fieldRef)) {
          fieldRef[0]?.focus(); // 数组类型，取第一个元素
        } else if (fieldRef) {
          fieldRef.focus(); // 单节点，直接聚焦
        } else {
          console.error(`Ref not found: ${refName}`);
        }
      });
    },

    whetherHide(formItem) {
      if (JSON.stringify(this.attr_hide) !== "[]") {
        return !this.attr_hide["hide_" + formItem];
      }
      return true;
    },
    //是否显示商品类型
    isGoodsType() {
      let plugin_ids = [140, 154, 157];

      if (plugin_ids.indexOf(this.form.goods.plugin_id) === -1) {
        return true;
      } else {
        return false;
      }
    },
    jumpUrl() {
      window.open(CktUrl);
    },
    // 添加默认值
    addDefaul() {
      if (JSON.stringify(this.attr_hide) !== "[]") {
        if (this.attr_hide.appoint_type) {
          this.form.goods.type = this.attr_hide.appoint_type;
        }
        if (this.attr_hide.appoint_need_address) {
          this.form.goods.need_address = this.attr_hide.appoint_need_address;
        }
      }
      if (!this.form.goods.display_order) {
        this.$set(this.form.goods, "display_order", 0);
      }
      if (!this.form.goods.category_to_option) {
        this.$set(this.form.goods, "category_to_option", [
          { goods_option_id: "" },
        ]);
      }
      if (!this.form.goods.type) {
        this.$set(this.form.goods, "type", 1);
      }
      if (!this.form.goods.need_address) {
        this.$set(this.form.goods, "need_address", 0);
      }
      // if (!this.form.goods.price) {
      //   this.$set(this.form.goods, "price", 0);
      // }
      // if (!this.form.goods.market_price) {
      //   this.$set(this.form.goods, "market_price", 0);
      // }
      // if (!this.form.goods.cost_price) {
      //   this.$set(this.form.goods, "cost_price", 0);
      // }
      if (!this.form.goods.sku) {
        this.$set(this.form.goods, "sku", "个");
      }
      // if (!this.form.goods.weight) {
      //   this.$set(this.form.goods, "weight", 0);
      // }
      if (!this.form.goods.volume) {
        this.$set(this.form.goods, "volume", 0);
      }
      if (!this.form.goods.reduce_stock_method) {
        this.$set(this.form.goods, "reduce_stock_method", 0);
      }
      if (!this.form.goods.withhold_stock) {
        this.$set(this.form.goods, "withhold_stock", 0);
      }
      if (!this.form.goods.is_hide) {
        this.$set(this.form.goods, "is_hide", 1);
      }
      // if (!this.form.goods.no_refund) {
      //   this.$set(this.form.goods, "no_refund", 0);
      // }
      if (!this.form.goods.virtual_sales) {
        this.$set(this.form.goods, "virtual_sales", 0);
      }

      //后补充
      if (!this.form.goods.is_stock) {
        this.$set(this.form.goods, "is_stock", 1);
      }
      // if (!this.form.goods.productType) {
      //   this.$set(this.form.goods, "productType", 1);
      // }
      if (!this.form.goods.other_name) {
        this.$set(this.form.goods, "other_name", "其它");
      }
    },
    // 商品分类回显
    goodsCategoryShow() {
      // 判断回显时商品类型是否存在，不存在根据category_level长度添加数据--this.judgeCategoryLevel()
      // 如果category_level的长度与返回的长度不一致，则不全默认值

      // 处理OriginCategory原始值,用于保存提交的数据
      if (this.form.goods.category_transforme) {
        this.OriginCategory = JSON.parse(
          JSON.stringify(this.form.goods.category_transforme)
        );
        // 检查item长度，补全分类层级
        if (this.form.category_level == 3) {
          this.OriginCategory.forEach((item) => {
            if (item.length === 2) {
              item.push({ id: "", level: 3 });
            }
          });
        } else if (this.form.category_level == 2) {
          this.OriginCategory.forEach((item) => {
            if (item.length === 1) {
              item.push({ id: "", level: 2 });
            }
          });
        }
      } else {
        this.judgeCategoryLevel();
      }
      if (!this.form.goods.category) {
        this.isShow = false;
      }

      // categoryList, 数据交互、显示
      if (this.form.goods.category_transforme) {
        if (this.form.goods.category_transforme.length) {
          if (this.form.goods.category_transforme[0].length) {
            // 分类处理逻辑
            for (item of this.form.goods.category_transforme) {
              // // 交换 id 和 name
              // item.forEach((cateItem) => {
              //   let id = cateItem.id;
              //   cateItem.id = cateItem.name;
              //   cateItem.name = id;
              // });
              // 格式化分类项数据
              item = item.map((el) => ({
                id: el.id,
                level: el.level,
                name: el.name,
              }));
              // 补全分类层级:
              // 判断是否有一级分类
              let firstLevel = item.find((threeItem) => threeItem.level === 1);
              if (this.form.category_level * 1 === 1 && !firstLevel) {
                item.push({ id: "", level: 1 });
              }
              // 判断是否有二级分类
              let secondLevel = item.find((threeItem) => threeItem.level === 2);
              if (this.form.category_level * 1 === 2 && !secondLevel) {
                item.push({ id: "", level: 2 });
              }
              // 判断是否有三级分类
              let threeLevel = item.find((threeItem) => threeItem.level === 3);
              if (this.form.category_level * 1 === 3 && !threeLevel) {
                item.push({ id: "", level: 3 });
              }
              this.categoryList.push(item);
              this.changeCategoryList = this.categoryList;
            }
          } else {
            this.judgeCategoryLevel();
          }
        } else {
          this.judgeCategoryLevel();
        }
      } else {
        this.judgeCategoryLevel();
        this.changeCategoryList = this.categoryList;
      }
      // console.log("=====listen OriginCategory======", this.OriginCategory);
      // console.log("=====listen categoryList======", this.categoryList);
    },
    // 获取分类值
    getCategoryValue() {
      this.category_list_box = [];
      this.categoryList.forEach((item, index) => {
        let filterCategry = [];
        filterCategry.push({ secondCategory: [] });
        filterCategry.push({ threeCategory: [] });
        this.category_list_box.push(filterCategry);
      });
    },
    // 初始化分类数据，根据分类等级
    judgeCategoryLevel() {
      switch (this.form.category_level * 1) {
        case 1:
          this.categoryList = [[{ id: "", level: 1 }]];
          this.OriginCategory = this.categoryList;
          break;
        case 2:
          this.categoryList = [
            [
              { id: "", level: 1 },
              { id: "", level: 2 },
            ],
          ];
          this.OriginCategory = this.categoryList;
          break;
        default:
          this.categoryList = [
            [
              { id: "", level: 1 },
              { id: "", level: 2 },
              { id: "", level: 3 },
            ],
          ];
          this.OriginCategory = this.categoryList;
          break;
      }
    },
    displaySelectMaterialPopup(fieldName = "thumb", type = 1) {
      if (fieldName != "video") {
        this.is_high = 1;
      } else {
        this.is_high = 0;
      }

      if (["other", "real_image", "wiring_diagram"].includes(fieldName)) {
        this.selNum = "more";
      } else {
        this.selNum = "one";
      }
      console.log("=======is_high===", this.is_high);
      this.formFieldName = fieldName;
      this.showSelectMaterialPopup = !this.showSelectMaterialPopup;
      this.materialType = String(type);
    },
    showUploadPopup(index, tabIndex, name, materialType) {
      this.selectedSpecDetailsIndex = index;
      this.selectedSpecDetailTabsIndex = tabIndex;
      this.showSelectMaterialPopup = !this.showSelectMaterialPopup;
      this.upload_type = name;
      this.materialType = materialType;
      this.formFieldName = name;
    },
    // 确认选择图片
    selectedMaterial(name, image, imageUrl) {
      let originalImageUrl = JSON.parse(JSON.stringify(imageUrl));
      if (name == "pdf") {
        this.eCatalogPdfName = imageUrl[0].filename;

        this.form.goods.e_catalog_pdf = imageUrl[0].attachment;
        this.pdf_page = [];
        this.showPdf = true;
        this.pdfPath = imageUrl[0].url;
      }
      // 保养手册
      if (name == "maintenance_doc") {
        this.form.goods.maintenance_doc = imageUrl[0].attachment;
        this.maintenanceDocName = imageUrl[0].filename;
      }
      // 安装指南
      if (name == "install_guide") {
        this.form.goods.install_guide = imageUrl[0].attachment;
        this.installGuideName = imageUrl[0].filename;
      }

      // 获取当前组合spec
      const specDetail = this.specDetails[this.selectedSpecDetailsIndex];
      // 获取当前modelType
      const modelType =
        specDetail?.modelTypes[this.selectedSpecDetailTabsIndex];
      // 获取当前option
      const option =
        this.specDetails[this.selectedSpecDetailsIndex]?.modelTypes[
          this.selectedSpecDetailTabsIndex
        ]?.option;

      if (name == "cad_plan_model") {
        option.cad_plan_model = imageUrl[0].attachment;
        option.cad_plan_modelName = imageUrl[0].filename;
      }

      if (name == "option_thumb") {
        option.thumb = imageUrl[0].attachment;
        option.thumbName = imageUrl[0].filename;
      }

      if (name == "video") {
        if (typeof imageUrl == "string") {
          this.form.goods.goods_video_link = imageUrl;
          this.form.goods.goods_video = imageUrl;
          return;
        }
        this.form.goods.goods_video_link = imageUrl[0].url;
        this.form.goods.goods_video = originalImageUrl[0].attachment;
      }
      if (name == "video_cover") {
        this.form.goods.video_image_link = imageUrl[0].url;
        this.form.goods.video_image = originalImageUrl[0].attachment;
      }
      if (name == "background_pic") {
        if (typeof imageUrl == "string") {
          this.form.goods.background_pic_link = imageUrl;
          this.form.goods.background_pic = imageUrl;
          return;
        }
        this.form.goods.background_pic_link = imageUrl[0].url;
        this.form.goods.background_pic = originalImageUrl[0].attachment;
      }
      if (name == "advert_pic") {
        if (typeof imageUrl == "string") {
          this.form.goods.advert_pic_link = imageUrl;
          this.form.goods.advert_pic = imageUrl;
          return;
        }
        this.form.goods.advert_pic_link = imageUrl[0].url;
        this.form.goods.advert_pic = originalImageUrl[0].attachment;
      }

      if (name == "d3model") {
        option.d3Max = imageUrl[0].attachment;
        option.d3MaxName = imageUrl[0].filename;
        option.d3MaxUrl = imageUrl[0].url;
      }

      if (Array.isArray(imageUrl)) {
        imageUrl = imageUrl.map((item) => {
          return item.url;
        });
      }

      if (this.formFieldName === "other") {
        if (this.goodsImagesChangeIndex === null) {
          if (!this.form.goods.thumb_url) {
            this.$set(this.form.goods, "thumb_url", []);
          }
          for (let item of originalImageUrl) {
            this.form.goods["thumb_url"].push({
              thumb_link: item.url,
              thumb: item.attachment,
              high_url: item.attachment_high,
            });
          }
          console.log(this.form.goods["thumb_url"]);
          /* for (let item of originalImageUrl) {
            this.goods_images.push({
              image_type:1,
              high_url: item.attachment_high,
              compressed_url: item.attachment,
            });
          }*/
        } else {
          // this.form.goods["thumb_url"][this.goodsImagesChangeIndex] = imageUrl[0];
          this.goodsImagesChangeIndex = null;
        }
      } else if (
        ["real_image", "wiring_diagram"].includes(this.formFieldName)
      ) {
        //多选上传图片通用，可以加到这里
        if (this.goodsImagesChangeIndex === null) {
          if (!this.form.goods[this.formFieldName]) {
            this.$set(this.form.goods, this.formFieldName, []);
          }
          for (let item of originalImageUrl) {
            this.form.goods[this.formFieldName].push({
              image_link: item.url,
              thumb: item.attachment,
              high_url: item.attachment_high,
            });
          }
          /*let image_type = "";
           if(this.formFieldName == "real_image"){
             image_type = 2;
           }else if(this.formFieldName == "wiring_diagram"){
             image_type = 3;
           }
          for (let item of originalImageUrl) {
            this.goods_images.push({
              image_type:image_type,
              high_url: item.attachment_high,
              compressed_url: item.attachment,

            });
          }*/
        } else {
          // this.form.goods[this.formFieldName][this.goodsImagesChangeIndex] = imageUrl[0];
          this.goodsImagesChangeIndex = null;
        }
      } else {
        this.form.goods[this.formFieldName] = originalImageUrl[0].attachment;
        if (this.formFieldName == "thumb") {
          this.form.goods["thumb_link"] = imageUrl[0];
        }
      }
      this.$forceUpdate();
    },
    // 点击图片选中
    sureSelectImg(val, id, item) {
      // console.log(val,id,item,'点击图片选中');
      // if(this.formFieldName == "video_cover"){
      //   this.form.goods.video_image = item.url
      // }
      // if(this.formFieldName == "thumb"){
      //   this.form.goods[this.formFieldName] = item.url
      // }
    },
    // 关闭上传弹窗的回调
    uploadmediaClose() {
      this.showSelectMaterialPopup = !this.showSelectMaterialPopup;
    },
    changeGoodsImage(index, val) {
      this.goodsImagesChangeIndex = index;
      this.displayUploadImagePopup("images");
    },
    // 视频删除
    removeVideo(type) {
      if (type == "video") {
        this.form.goods.goods_video = "";
        this.form.goods.goods_video_link = "";
      }
      if (type == "image") {
        this.form.goods.video_image = "";
        this.form.goods.video_image_link = "";
      }
      this.$forceUpdate();
    },
    // 移除商品效果图
    removeMainImage(itemIndex) {
      this.previewImgList.splice(itemIndex, 1);
      this.form.goods.main_url = this.previewImgList;
    },
    // 移除商品效果图
    removeGoodsImage(itemIndex) {
      this.previewImgList.splice(itemIndex, 1);
      this.form.goods.thumb_url = this.previewImgList;
    },
    // 移除商品实拍图
    removeGoodsRealImage(itemIndex) {
      this.previewImgList.splice(itemIndex, 1);
      this.form.goods.real_image = this.previewImgList;
    },
    // 移除走线示意图
    removeWiringDiagramImage(itemIndex) {
      this.previewImgList.splice(itemIndex, 1);
      this.form.goods.wiring_diagram = this.previewImgList;
    },
    // 移除商品图册
    removeAtlasImage(itemIndex) {
      this.previewImgList.splice(itemIndex, 1);
      this.form.goods.atlas.data = this.previewImgList;
    },
    // 移除保养手册
    removeMaintenanceImage(itemIndex) {
      this.previewImgList.splice(itemIndex, 1);
      this.form.goods.maintenance_doc.data = this.previewImgList;
    },
    // 移除安装指南
    removeInstallImage(itemIndex) {
      this.previewImgList.splice(itemIndex, 1);
      this.form.goods.install_guide.data = this.previewImgList;
    },
    // 移除规格组合明细-安装指南
    removeOptionInstallImage(itemIndex) {
      this.previewImgList.splice(itemIndex, 1);
      const option =
        this.specDetails[this.selectedSpecDetailsIndex]?.modelTypes[
          this.selectedSpecDetailTabsIndex
        ]?.option;
      option.install_guide.data = this.previewImgList;
    },
    // 移除规格组合明细-走线图
    removeOptionWiringDiagramImage(itemIndex) {
      this.previewImgList.splice(itemIndex, 1);
      const option =
        this.specDetails[this.selectedSpecDetailsIndex]?.modelTypes[
          this.selectedSpecDetailTabsIndex
        ]?.option;
      option.wiring_diagram = this.previewImgList;
    },

    removeBackgroundPic() {
      this.form.goods.background_pic = "";
      this.form.goods.background_pic_link = "";
      this.$forceUpdate();
    },
    removeAdvertPic() {
      this.form.goods.advert_pic = "";
      this.form.goods.advert_pic_link = "";
      this.$forceUpdate();
    },
    validate() {
      if (this.nationList.length) {
        this.form.goods.title =
          this.form.goods.lang[this.nationList[0].value].title;
        this.form.goods.alias =
          this.form.goods.lang[this.nationList[0].value].alias;
      }
      let submitCategoryList = [];
      this.OriginCategory.forEach((element) => {
        element = element.map((item) => {
          delete item.name;
        });
      });
      this.OriginCategory.forEach((item) => {
        let filterCatrgorys = item.filter((value, index, arr) => {
          return value.id !== "";
        });
        submitCategoryList.push(filterCatrgorys);
      });
      // 过滤空数组
      let filterSubmitCategoryList = [];
      submitCategoryList.forEach((element, index) => {
        if (element.length !== 0) {
          filterSubmitCategoryList.push(element);
        }
      });
      // 过滤商品分类重复的数据
      let idList = [];
      filterSubmitCategoryList.forEach((item) => {
        if (item.length >= this.form.category_level) {
          idList.push(item[this.form.category_level * 1 - 1].id);
        } else {
          idList = [];
        }
      });
      if (!this.attr_hide.hide_category) {
        if (new Set(idList).size !== idList.length || idList.length == 0) {
          this.$message({
            message: this.lang.goods.error_category_same,
            type: "warning",
          });
          this.categoryRegular = false;
          this.$refs.category[0].focus();
          return false;
        } else {
          this.categoryRegular = true;
        }
      } else {
        this.categoryRegular = true;
      }

      // 基础信息校验
      if (!this.tipValidator()) return false;
      // 检查规格组和specDetails详细信息校验
      if (!this.validateSpecAndDetails()) return false;

      if (this.form.goods.type == 1) {
        this.form.goods.need_address = 0;
      }
      /*  let thumb_url = [];
      // 过滤其他图片http字段
      if (this.form.goods.thumb_url !== undefined) {
        for (let item of this.form.goods.thumb_url) {
          thumb_url.push(item.thumb);
        }
      }
      let real_image = [];
      // 过滤其他图片http字段
      if (this.form.goods.real_image !== undefined) {
        for (let item of this.form.goods.real_image) {
          real_image.push(item.thumb);
        }
      }
      let wiring_diagram = [];
      // 过滤其他图片http字段
      if (
        this.form.goods?.wiring_diagram &&
        this.form.goods?.wiring_diagram !== undefined
      ) {
        for (let item of this.form.goods.wiring_diagram) {
          wiring_diagram.push(item.thumb);
        }
      }*/

      console.log(
        "=======saveGoods filterSubmitCategoryList=======",
        this.OriginCategory
      );

      const price =
        this.specDetails?.[0]?.modelTypes?.[0]?.option?.product_price ?? 0;

      if (
        this.categoryRegular &&
        this.sortRegular &&
        this.titleRegular &&
        this.virtualSalesRegular &&
        this.stockRegular &&
        // this.thumbRegular &&
        this.form.goods.sku
      ) {
        // 商品图册过滤base64
        if (this.form.goods.atlas) {
          this.form.goods.atlas.data = this.form.goods.atlas.data.map((x) => {
            return this.filterObject(x, ["pdfPage", "inPpt"]);
          });
        }

        // 主图取第一个
        // this.form.goods.thumb = this.form.goods.main_url?.[0]?.thumb;

        let saveGoods = {
          is_lang_set: typeof is_lang_set != "undefined" ? is_lang_set : "",
          brand_id: this.form.goods.brand_id ? this.form.goods.brand_id : "",
          category: filterSubmitCategoryList,
          display_order: this.form.goods.display_order,
          title: this.form.goods.title,
          alias: this.form.goods.alias || "",
          type: this.form.goods.type,
          need_address: this.form.goods.need_address,
          sku: this.form.goods.sku,

          is_recommand: this.form.goods.is_recommand,
          is_new: this.form.goods.is_new,
          // is_hot: this.form.goods.is_hot,
          // is_discount: this.form.goods.is_discount,

          // thumb: this.form.goods.thumb,
          thumb_url: this.form.goods.thumb_url || [], //商品效果图
          price: price,
          // price: this.form.goods.price,
          // market_price: this.form.goods.market_price,
          // cost_price: this.form.goods.cost_price,
          goods_sn: this.form.goods.goods_sn || "",
          product_sn: this.form.goods.product_sn || "",
          // weight: this.form.goods.weight,
          volume: this.form.goods.volume,
          virtual_sales: this.form.goods.virtual_sales,
          stock: this.form.goods.stock,
          reduce_stock_method: this.form.goods.reduce_stock_method,
          is_hide: this.form.goods.is_hide,
          // no_refund: this.form.goods.no_refund,
          video_image: this.form.goods.video_image || "",
          goods_video: this.form.goods.goods_video || "", //商品视频
          withhold_stock: this.form.goods.withhold_stock,
          category_to_option: this.form.goods.category_to_option,
          hide_goods_sales: this.form.goods.hide_goods_sales,
          hide_goods_sales_alone: this.form.goods.hide_goods_sales_alone,
          hide_goods_pic: this.form.goods.hide_goods_pic || 0,
          video_url: this.form.goods.video_url || "",
          background_pic: this.form.goods.background_pic || "",
          advert_pic: this.form.goods.advert_pic || "",
          lang: this.form.goods.lang,

          goods_relation: this.form.goods.goods_relation || [], //关联商品-关联关系 {id,is_relation} 属性is_relation: 1.双向关联 0.单向关联
          e_catalog_pdf: this.form.goods.e_catalog_pdf || "", //商品图册
          // maintenance_doc: this.form.goods.maintenance_doc || "", //保养手册
          // install_guide: this.form.goods.install_guide || "", //安装指南
          is_stock: this.form.goods.is_stock,
          // productType: this.form.goods.productType,
          craft_materials: this.form.goods.craft_materials, //工艺材质
          goods_style: this.form.goods.goods_style, //商品风格
          keywords: this.form.goods.keywords || "", //标签关键词
          // structure: this.form.goods.structure, //材质说明(放规格里面了)
          warranty: this.form.goods.warranty, //质保期
          // design: this.form.goods.design, //设计说明
          lead_time:
            this.form.goods.is_stock == 1 ? this.form.goods.lead_time : null, //生产货期
          real_image: this.form.goods.real_image || [], //实拍图
          wiring_diagram: this.form.goods.wiring_diagram || [], //走线示意图
          other_name: this.form.goods.other_name || "其它", //走线示意图自定义名称
          // main_url: this.form.goods.main_url || [],
          pdf_page: this.form.goods.pdf_page || [],
          option: {
            has_option: 1,
            specs: this.goodsSpecs,
            option: this.specDetails,
          },
          // goods_images:this.goods_images,

          // 商品图册，保养手册，安装指南
          atlas: this.form.goods.atlas,
          maintenance_doc: this.form.goods.maintenance_doc,
          install_guide: this.form.goods.install_guide,
        };

        // alert(JSON.stringify(saveGoods.image_names))

        if (!this.attr_hide.hide_status) {
          saveGoods.status = this.form.goods.status;
        }

        return saveGoods;
      } else {
        return false;
      }
    },
    tipValidator() {
      if (!this.sortRegular) {
        this.$message({
          message: this.lang.goods.error_sort_empty,
          type: "warning",
        });
        return false;
      } else if (!this.titleRegular || !this.form.goods.title) {
        //商品名称
        this.$message({
          message: this.lang.goods.error_title_empty,
          type: "warning",
        });
        this.$refs.title.focus();
        return false;
      } else if (this.form.goods.craft_materials.length == 0) {
        //工艺材质
        this.$message.warning("请输入工艺/材质");
        this.$refs.craft_materials.focus();
        return false;
      } else if (this.form.goods.goods_style.length == 0) {
        //商品风格
        this.$message.warning("请输入商品风格");
        this.$refs.goods_style.focus();
        return false;
      } else if (!this.form.goods.sku) {
        //商品单位
        this.$message({
          message: this.lang.goods.error_sku_empty,
          type: "warning",
        });
        this.$refs.sku.focus();
        return false;
      } else if (!this.form.goods.warranty) {
        //质保期
        this.$message.warning("请输入质保期");
        this.$refs.warranty.focus();
        return false;
        // } else if (!this.form.goods.structure) {
        //   //材质说明
        //   this.$message.warning("请输入材质说明");
        //   this.$refs.structure.focus();
        //   return false;
      } else if (!this.form.goods.stock || !this.stockRegular) {
        //库存、产能
        const is_stock = this.form.goods.is_stock;
        this.$message({
          message:
            is_stock == 1
              ? this.lang.goods.error_stock_empty_1
              : this.lang.goods.error_stock_empty,
          type: "warning",
        });
        this.$refs.stock.focus();
        return false;
      } else if (
        !this.form.goods.atlas ||
        !this.form.goods.atlas.data ||
        this.form.goods.atlas?.data?.length == 0
      ) {
        //商品图册
        this.$message.warning("请上传商品图册");
        this.$refs.goods_atlas.focus();
        return false;
        // } else if (!this.form.goods.e_catalog_pdf) {
        //   //商品图册
        //   this.$message.warning("请上传商品图册");
        //   return false;
        // } else if (!this.form.goods.maintenance_doc) {
        //   //保养手册
        //   this.$message.warning("请输入保养手册");
        //   return false;
      } else if (this.form.goods.is_stock == 1 && !this.form.goods.lead_time) {
        //生产货期
        this.$message.warning("请输入生产货期");
        this.$refs.lead_time.focus();
        return false;
        // } else if (!this.form.goods.install_guide) {
        //   //安装指南
        //   this.$message.warning("请输入安装指南");
        //   return false;
        // } else if (
        //   !this.form.goods.main_url ||
        //   this.form.goods.main_url?.length == 0
        // ) {
        //   //商品主图
        //   this.$message({
        //     message: this.lang.goods.error_thumb_empty,
        //     type: "warning",
        //   });
        //   this.$refs.main_url.focus();
        //   return false;
      } else if (
        !this.virtualSalesRegular &&
        this.form.goods.virtual_sales !== ""
      ) {
        this.$message({
          message: this.lang.goods.error_virtual_sales_empty,
          type: "warning",
        });
        return false;
      } else if (!this.categoryRegular) {
        this.$message.warning(this.lang.goods.error_category_empty);
        return false;
      }
      // else if (!this.thumbRegular) {
      //   this.$message.warning(this.lang.goods.error_thumb_empty);
      //   return false;
      // }
      // if(!this.form.goods.virtual_sales){this.virtualSalesRegular = false }
      // if(!this.form.goods.stock){this.stockRegular = false }
      return true; // 所有校验通过
    },
    // extraDate(){
    //   return {
    //     'extra':"额外数据"
    //   }
    // },
    // 监听一级商品分类
    onChangeFirst(value, itemIndex, firstValue) {
      if (!firstValue) {
        this.category_list_box[itemIndex].secondCategory = [];
        this.category_list_box[itemIndex].threeCategory = [];
        this.categoryList[itemIndex].forEach((item) => {
          item.id = "";
        });
      }
      this.categoryList[itemIndex].forEach((item, key) => {
        if (key == 0) {
          item.id = item.id;
        } else {
          item.id = "";
        }
      });
      // 点击一级分类获取二级分类数据
      this.form.category_list.forEach((item) => {
        if (item.id == value) {
          // this.secondCategory = item.childrens
          this.category_list_box[itemIndex].secondCategory = item.childrens;
          return;
        }
      });
      this.listenCategory(value, itemIndex, 0);
    },
    // 监听二级商品分类
    onFocusSecond(itemIndex, secondValue) {
      let category_list = JSON.parse(JSON.stringify(this.form.category_list));
      let secondChildrens = [];
      if (secondValue.id) {
        this.secondChildrensIsshow = false;
        category_list.forEach((item, index) => {
          if (item.id == secondValue.id) {
            secondChildrens.push(...item.childrens);
            return;
          }
        });
        this.category_list_box[itemIndex].secondCategory = secondChildrens;
        this.secondChildrensIsshow = true;
      }
    },
    onChangeSecond(value, itemIndex, secondValue) {
      if (!secondValue) {
        this.category_list_box[itemIndex].threeCategory = [];
      }
      this.categoryList[itemIndex].forEach((item, key) => {
        if (key === 2) {
          item.id = "";
        }
      });
      // 点击二级分类获取三级分类数据
      if (this.category_list_box[itemIndex].secondCategory) {
        this.category_list_box[itemIndex].secondCategory.forEach(
          (item, index) => {
            if (item.id == value) {
              // this.threeCategory = item.childrens
              this.category_list_box[itemIndex].threeCategory = item.childrens;
              return;
            }
          }
        );
      }
      this.listenCategory(value, itemIndex, 1);
    },
    // 监听三级商品分类
    onFocusThree(itemIndex, secondValue, oneValue) {
      let category_list = JSON.parse(JSON.stringify(this.form.category_list));
      let threeChildrens = [];
      if (secondValue.id) {
        this.threeChildrensIsshow = false;
        category_list.forEach((item, index) => {
          if (item.id == oneValue.id) {
            item.childrens.forEach((el, key) => {
              if (el.id == secondValue.id) {
                threeChildrens.push(...el.childrens);
                return;
              }
            });
            return;
          }
        });
        this.category_list_box[itemIndex].threeCategory = threeChildrens;
        this.threeChildrensIsshow = true;
      }
    },
    onChangeThree(value, itemIndex) {
      this.listenCategory(value, itemIndex, 2);
    },
    listenCategory(value, itemIndex, type) {
      let info = false;
      // 检查分类数据是否有改变
      this.changeCategoryList[itemIndex].forEach((el, key) => {
        if (el.id === this.categoryList[itemIndex][key].id) {
          info = true;
          return;
        }
      });
      if (this.isShow) {
        if (info) {
          // 更新分类数据
          let categoryBoxList = [];
          this.OriginCategory[itemIndex].forEach((item, key) => {
            if (type == 2) {
              // 仅更新第 3 层
              if (key === 2) {
                item = this.categoryList[itemIndex][key];
              }
            } else if (type == 1) {
              // 更新第 2 层和第 3 层
              if (key === 2 || key === 1) {
                item = this.categoryList[itemIndex][key];
              }
            } else if (type == 0) {
              // 更新所有层级
              if (key === 2 || key === 1 || key === 0) {
                item = this.categoryList[itemIndex][key];
                // item.id = ""
              }
            }

            categoryBoxList.push(item);
          });
          this.OriginCategory[itemIndex] = categoryBoxList;
        } else {
          // 当分类数据发生变化时，直接同步更新
          this.OriginCategory[itemIndex] = this.categoryList[itemIndex];
        }
      }
      console.log("=====listen OriginCategory======", this.OriginCategory);
    },

    showThree(index, tabIndex, detail) {
      const { title, option } = detail;

      this.editOption = option;

      this.d3ModelData.title = title;
      this.d3ModelData.d3ModelUrl = option.d3model_url;
      this.d3ModelData.model_param = JSON.parse(
        JSON.stringify(option.model_param)
      );

      /** 新字段 */
      this.d3ModelData.d3ModelUrl_ori = option.d3ModelUrl_ori;

      // 兼容旧版 没有该字段用 d3ModelUrl 替代
      if (!this.d3ModelData.d3ModelUrl_ori)
        this.d3ModelData.d3ModelUrl_ori = this.d3ModelData.d3ModelUrl;
      this.d3ModelData.d3ModelUrl_buffer = option.d3ModelUrl_buffer;
      this.d3ModelData.d3ModelUrl_ori_file = option.d3ModelUrl_ori_file;

      // 长宽高
      console.log("========d3ModelData=======", option);
      const { length, width, height } = option;
      if (!length || !width || !height) {
        this.$message.error("请先填入商品规格的长宽高");
        return;
      }
      this.d3ModelData.length = length;
      this.d3ModelData.width = width;
      this.d3ModelData.height = height;

      // 模型减面
      // this.d3ModelData.modelSlim = option.modelSlim;
      this.d3ModelData.thumb3dModelUrl_file = option.thumb3dModelUrl_file;

      // 配色方案
      this.d3ModelData.colorPlan = option.colorPlan;
      const spceItem = this.specDetails[this.selectedTopIndex];
      this.d3ModelData.productType = spceItem?.productType;
      this.d3ModelData.modelType = spceItem?.modelTypes[tabIndex]?.modelType;

      console.log("========d3ModelData=======", this.d3ModelData);
      console.log("========option=======", option);
      console.log("========spceItem=======", spceItem);

      /** 继承部件 */
      const specDetail = this.specDetails[index];
      const modelType = specDetail?.modelTypes[0]; // 独立位
      this.d3ModelData.tabIndex = tabIndex;
      this.baseModelParam = [];
      if (index == 0) {
        if (tabIndex > 0) {
          this.baseModelParam = modelType.option.model_param;
        }
      } else {
        const firstSpecDetail = this.specDetails[0];
        const firstModelType = firstSpecDetail?.modelTypes[0];

        if (tabIndex == 0) {
          this.baseModelParam = firstModelType.option.model_param;
        } else {
          this.baseModelParam = modelType.option.model_param.length
            ? modelType.option.model_param
            : firstModelType.option.model_param;
        }
      }

      if (
        this.d3ModelData.d3ModelUrl_ori ||
        this.d3ModelData.d3ModelUrl_ori_file
      ) {
        this.threeVisible = true;
      } else {
        let fileInput = this.$refs["fileInput_" + option.id];
        if (Array.isArray(fileInput)) fileInput = fileInput[0];
        fileInput?.click();
      }
    },

    /** 直接读取glb file */
    glbInputChange(event) {
      const file = event.target.files[0];

      if (file) {
        // 没取到值用50M
        const fileSizeKb = file.size / 1024;
        const limit = this.form.limit_file_size || 50 * 1024;
        if (fileSizeKb > limit) {
          this.$message.error(`文件大小不能超过${(limit / 1024).toFixed(0)}M`);
        } else {
          this.d3ModelData.d3ModelUrl_ori_file = file;
          // aman anchor update
          this.threeVisible = true;
        }

        let fileInput = this.$refs["fileInput_" + this.editOption.id];
        if (Array.isArray(fileInput)) fileInput = fileInput[0];
        fileInput.value = "";
      }
    },

    //#region 上传GLB相关
    //--------
    handleSuccessGlb(file) {},
    //--------
    //#endregion

    onThreeSave() {
      if (!this.editOption) return;
      this.editOption.model_param = this.d3ModelData.model_param;
      this.editOption.d3ModelUrl_ori_file =
        this.d3ModelData.d3ModelUrl_ori_file;

      this.editOption.d3ModelUrl_buffer = this.d3ModelData.d3ModelUrl_buffer;

      this.editOption.thumb3dModelUrl_buffer =
        this.d3ModelData.thumb3dModelUrl_buffer;

      this.editOption.thumb3dModelUrl_file =
        this.d3ModelData.thumb3dModelUrl_file;

      // 配色方案
      this.editOption.colorPlan = this.d3ModelData.colorPlan;

      // 减面模型面数
      this.editOption.total_face = this.d3ModelData.total_face;

      console.log("===onThreeSave===", this.editOption);

      this.threeWarningSign++;
    },

    openImageInput(is_high, img_type, hasPdf = false) {
      this.uploadUrl = this.uploadUrl.replace("=dwg", "=image");
      this.uploadUrl = this.uploadUrl.replace("=zip", "=image");
      this.uploadParams.is_high = is_high;
      this.img_type = img_type;
      this.imageInput_el_multiple = true;
      this.imageInput_el_accept = hasPdf
        ? ".png,.jpg,.jpeg,.pdf"
        : ".png,.jpg,.jpeg";
      // this.imageInput_el_autoUpload = !hasPdf;
      this.imageInput_el_autoUpload = false; // 全改成手动上传
      this.$nextTick(() => {
        let imageInput_el = this.$refs.imageInput_el;
        imageInput_el?.click();
      });
    },

    openImageInput2(index, tabIndex, name, materialType) {
      this.uploadUrl = this.uploadUrl.replace("=dwg", "=image");
      this.uploadUrl = this.uploadUrl.replace("=zip", "=image");
      this.uploadParams.is_high = 1;
      this.uploadParams.thumb_type = "goodsmain";
      this.img_type = name;
      this.selectedSpecDetailsIndex = index;
      this.selectedSpecDetailTabsIndex = tabIndex;
      this.imageInput_el_multiple = false;
      this.formFieldName = name;
      this.imageInput_el_autoUpload = false;
      this.$nextTick(() => {
        let imageInput_el = this.$refs.imageInput_el;
        imageInput_el?.click();
      });
    },

    openImageInput3(index, tabIndex, name, materialType) {
      this.uploadParams.is_high = 1;
      this.img_type = name;
      this.selectedSpecDetailsIndex = index;
      this.selectedSpecDetailTabsIndex = tabIndex;
      this.imageInput_el_multiple = false;
      // cad图纸
      if (materialType == "9") {
        this.refFileUpload_accept = ".dwg";
        this.uploadUrl = this.uploadUrl.replace("=image", "=dwg");
      }
      // 3DMax文件
      if (materialType == "10") {
        this.refFileUpload_accept = ".max,.3dx,.fbx,.obj";
        this.uploadUrl = this.uploadUrl.replace("=image", "=zip");
      }
      this.$nextTick(() => {
        let refFileUpload = this.$refs.refFileUpload;
        refFileUpload?.click();
      });
    },

    onImageInputChange(event) {
      try {
        const file = event.target.files[0];
        if (file) {
          this.uploadImage(file);
        }
      } finally {
        let imageInput = this.$refs.imageInput;
        if (imageInput) imageInput.value = "";
      }
    },

    uploadImage(file) {
      let loading = this.$loading({
        target: document.querySelector(".content"),
        background: "rgba(0, 0, 0, 0)",
      });

      const fd = new FormData();
      fd.append("file", file);
      fd.append("thumb_type", this.uploadParams.thumb_type);
      fd.append("is_high", this.uploadParams.is_high);

      this.$http
        .post(uploadUrl, fd)
        .then(
          function (response) {
            if (response.data.result) {
              this.$message.success(response.data.msg);
              console.log(response.data.data);
            } else {
              this.$message({
                message: response.data.msg,
                type: "error",
              });
            }
          },
          function (response) {
            this.$message({
              message: response.data.msg,
              type: "error",
            });
          }
        )
        .finally(() => {
          loading.close();
        });
    },

    async handleSuccess(res, file) {
      console.log("===res===", res, this.img_type);
      if (res.result == 0) {
        this.$message.error(res.msg);
        return;
      }
      const option =
        this.specDetails[this.selectedSpecDetailsIndex]?.modelTypes[
          this.selectedSpecDetailTabsIndex
        ]?.option;
      // console.log("上传文件的option：", option);

      switch (this.img_type) {
        case "thumb": // 商品主图
          this.form.goods.thumb_link = res.data.webp_thumb_absolute_path;

          this.form.goods.thumb = res.data.webp_thumb_path;

          // this.form.goods.main_url = {
          //   thumb: res.data.webp_thumb_path,
          //   main_thumb: res.data.webp_path,
          // };

          if (!this.form.goods.main_url) {
            this.$set(this.form.goods, "main_url", []);
          }

          this.form.goods["main_url"].push({
            thumb_link: res.data.webp_absolute_path,
            thumb: res.data.webp_thumb_path,
            main_thumb: res.data.webp_path,
            high_url: res.data.high_relative_path,

            // thumb_link: res.data.url,
            // thumb: res.data.attachment,
            // main_thumb: res.data.attachment,
            // high_url: res.data.high_relative_path,
          });
          // this.$refs.rform.validateField("main_url"); //手动触发校验，更新校验
          break;

        case "thumb_url": // 商品效果图
          if (!this.form.goods.thumb_url) {
            this.$set(this.form.goods, "thumb_url", []);
          }

          this.form.goods["thumb_url"].push({
            thumb_link: res.data.webp_absolute_path,
            thumb: res.data.webp_thumb_path,
            main_thumb: res.data.webp_path,
            high_url: res.data.high_relative_path,
          });

          break;

        case "option_thumb": // 白底图
          option.thumb = res.data.webp_thumb_absolute_path;
          option.thumbName = res.data.name;
          option.thumb_url =
            {
              thumb: res.data.webp_thumb_absolute_path,
              main_thumb: res.data.webp_path,
              high_url: res.data.high_relative_path,
            } || {};
          console.log("====end==", option);
          // if(!option.image_names) option.image_names = {};
          // option.image_names.white_original_name = res.data.name;
          break;

        case "cad_plan_model": // cad图纸
          option.cad_plan_model = res.data.attachment;
          option.cad_plan_modelName = res.data.name;
          console.log("====end==", option);
          // if(!option.image_names) option.image_names = {};
          // option.image_names.cad_original_name = res.data.name;
          break;

        case "d3model": // 3Dmax模型
          option.d3Max = res.data.attachment;
          option.d3MaxName = res.data.name;
          option.d3MaxUrl = res.data.url;
          console.log("====end==", option);
          // if(!option.image_names) option.image_names = {};
          // option.image_names.d3max_original_name = res.data.name;
          break;

        case "real_image": // 商品实拍图
          if (!this.form.goods.real_image) {
            this.$set(this.form.goods, "real_image", []);
          }
          this.form.goods["real_image"].push({
            thumb_link: res.data.webp_absolute_path,
            thumb: res.data.webp_thumb_path,
            main_thumb: res.data.webp_path,
            high_url: res.data.high_relative_path,
          });
          break;

        case "wiring_diagram": // 商品基础信息-走线示意图(不上传了)
          if (!this.form.goods.wiring_diagram) {
            this.$set(this.form.goods, "wiring_diagram", []);
          }
          this.form.goods["wiring_diagram"].push({
            thumb_link: res.data.webp_absolute_path,
            thumb: res.data.webp_thumb_path,
            main_thumb: res.data.webp_path,
            high_url: res.data.high_relative_path,
          });
          break;

        case "atlas": // 商品图册
          if (!this.form.goods.atlas) {
            this.$set(this.form.goods, "atlas", { isPdf: 0, data: [] });
          }

          if (!res.data.name.endsWith(".pdf")) {
            if (this.form.goods.atlas.isPdf == 1) {
              this.form.goods.atlas.data = [];
              this.currentPreviewImg = null;
            }

            this.form.goods.atlas.isPdf = 0;

            this.form.goods.atlas.data.push({
              thumb_link: res.data.url,
              thumb: res.data.attachment,
              main_thumb: res.data.attachment,
              high_url: res.data.high_relative_path,
              inPpt: 0,
            });
          } else {
            console.log(this.uploadUrl);
            this.form.goods.atlas.pdfUrl = res.data.url;
            this.form.goods.atlas.isPdf = 1;
          }

          break;

        case "maintenance_doc":
          // 多张图片预览组件里面的上传
          if (!this.form.goods.maintenance_doc) {
            this.$set(this.form.goods, "maintenance_doc", {
              isPdf: 0,
              data: [],
            });
          }

          // if (this.form.goods.maintenance_doc.isPdf==1) {
          //   this.form.goods.maintenance_doc.data = [];
          //   this.currentPreviewImg = null;
          // }

          this.form.goods.maintenance_doc.isPdf = 0;

          this.form.goods.maintenance_doc.data.push({
            thumb_link: res.data.url,
            thumb: res.data.attachment,
            main_thumb: res.data.attachment,
            high_url: res.data.high_relative_path,
          });

          break;

        // 规格组合明细-安装指南
        case "option_install_guide":
          if (!option.install_guide) {
            this.$set(option, "install_guide", {
              isPdf: 0,
              data: [],
            });
          }
          // if (option.install_guide.isPdf==1) {
          //   option.install_guide.data = [];
          //   this.currentPreviewImg = null;
          // }
          option.install_guide.isPdf = 0;
          option.install_guide.data.push({
            thumb_link: res.data.url,
            thumb: res.data.attachment,
            main_thumb: res.data.attachment,
            high_url: res.data.high_relative_path,
          });
          break;
        // 规格组合明细-走线图
        case "option_wiring_diagram":
          if (!option.wiring_diagram) {
            this.$set(option, "wiring_diagram", []);
          }
          option.wiring_diagram.push({
            thumb_link: res.data.webp_absolute_path,
            thumb: res.data.webp_thumb_path,
            main_thumb: res.data.webp_path,
            high_url: res.data.high_relative_path,
          });
          break;

        default:
          // 处理默认情况
          console.log("未知图片类型");
      }
    },

    handleExceed(files, fileList) {},

    handlePreview(file) {},

    async beforeUpload(file) {
      const { name, size } = file;

      if (name.endsWith(".pdf")) {
        return true;
      }

      const mb = size / 1024 / 1024;

      // 大于10MB压缩
      if (mb > 10) {
        const smallFile = await this.compressImage(file, 3000, 3000);
        console.log(`===压缩前===${file.name}===${mb.toFixed(2)}MB`);
        console.log(
          `===压缩后===${smallFile.name}===${(
            smallFile.size /
            1024 /
            1024
          ).toFixed(2)}MB`
        );
        return smallFile;
      } else {
        return true;
      }
    },

    async compressImage(file, maxWidth, maxHeight) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (event) => {
          const img = new Image();
          img.onload = () => {
            const canvas = document.createElement("canvas");
            const ctx = canvas.getContext("2d");
            const MAX_WIDTH = maxWidth; // 设置最大宽度
            const MAX_HEIGHT = maxHeight; // 设置最大高度
            let width = img.width;
            let height = img.height;
            // 计算新的宽度和高度
            if (width > height) {
              if (width > MAX_WIDTH) {
                height *= MAX_WIDTH / width;
                width = MAX_WIDTH;
              }
            } else {
              if (height > MAX_HEIGHT) {
                width *= MAX_HEIGHT / height;
                height = MAX_HEIGHT;
              }
            }
            canvas.width = width;
            canvas.height = height;
            ctx.drawImage(img, 0, 0, width, height);
            // 以指定质量压缩图片
            const dataUrl = canvas.toDataURL("image/jpeg", 1);
            resolve(this.base64ToFile(dataUrl, file.name));
          };
          img.src = event.target.result;
        };
        reader.readAsDataURL(file);
      });
    },

    base64ToFile(base64String, filename) {
      // 分割 Base64 字符串，获取 MIME 类型和数据部分
      const [header, data] = base64String.split(",");
      const mimeType = header.match(/:(.*?);/)[1]; // 获取 MIME 类型
      const byteCharacters = atob(data); // 解码 Base64 数据
      const byteNumbers = new Uint8Array(byteCharacters.length);
      // 将解码后的字符转换为字节数组
      for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i);
      }
      // 创建 Blob 对象
      const blob = new Blob([byteNumbers], { type: mimeType });
      // 创建 File 对象
      const file = new File([blob], `压缩_${filename}`, { type: mimeType });

      return file;
    },

    handleProgress(event, file, fileList) {
      // this.uploadProgress = Math.floor((event.loaded / event.total) * 100) || 0; // 计算并更新上传进度

      const progress = Math.floor((event.loaded / event.total) * 100) || 0;

      if (!this.fileLoaded) {
        this.fileLoaded = 1;
      }

      this.uploadProgress = Math.floor(
        (progress / fileList.length) * this.fileLoaded
      );

      if (progress == 100) {
        this.fileLoaded++;
      }

      if (this.fileLoaded > fileList.length) this.fileLoaded = 0;
    },

    handleChange(file, fileList) {
      const fn = async () => {
        // 检查是否有 PDF 文件
        const hasPdf = fileList.some(
          (item) =>
            item.name.endsWith(".pdf") || item.type === "application/pdf"
        );

        const isCad = fileList.some((item) => item.name.endsWith(".dwg"));

        const isMax = fileList.some((item) => {
          return (
            item.name.endsWith(".max") ||
            item.name.endsWith(".3dx") ||
            item.name.endsWith(".fbx") ||
            item.name.endsWith(".obj")
          );
        });

        const option =
          this.specDetails[this.selectedSpecDetailsIndex]?.modelTypes[
            this.selectedSpecDetailTabsIndex
          ]?.option;

        if (hasPdf) {
          if (fileList.length > 1) {
            this.$refs.upload.clearFiles();
            // this.$refs.upload.handleStart(file);
            this.$message.warning("选择 PDF 文件时只能单选！请重新选择！");
            return;
          } else {
            const { size } = file;

            const mb = size / 1024 / 1024;

            if (mb > 500) {
              this.$message.error("上传pdf文件大小不能超过 500MB!");
              this.$refs.upload.clearFiles();
              return;
            }

            if (file.status != "ready") {
              this.$refs.upload.clearFiles();
              this.visible = false;
              return; // 防止上传成功或失败后再执行一次
            }

            switch (this.formFieldName) {
              case "atlas": {
                this.uploadUrl = this.uploadUrl.replace("=image", "=pdf");
                setTimeout(() => {
                  this.uploadProgress = 0;
                  this.visible = true;
                  this.$refs.upload.submit();
                }, 100);

                const images = await this.renderPdf(file.raw);

                if (!this.form.goods.atlas) {
                  this.$set(this.form.goods, "atlas", { isPdf: 1, data: [] });
                } else {
                  this.form.goods.atlas.isPdf = 1;
                  this.form.goods.atlas.data = [];
                  this.currentPreviewImg = null;
                }

                for (let i = 0; i < images.length; i++) {
                  const { large_thumb, small_thumb } = images[i];

                  this.form.goods.atlas.data.push({
                    thumb_link: small_thumb,
                    thumb: small_thumb,
                    main_thumb: small_thumb,
                    high_url: large_thumb,
                    pdfPage: i + 1,
                    inPpt: 0,
                  });
                }

                this.openImagePreviewUpload(
                  this.form.goods.atlas,
                  "atlas",
                  "商品图册",
                  "atlas"
                );
                break;
              }

              case "maintenance_doc": {
                this.uploadUrl = this.uploadUrl.replace("=pdf", "=image");
                const images = await this.renderPdf(file.raw);
                this.$refs.upload.clearFiles();

                try {
                  this.uploadProgress = 0;
                  this.visible = true;

                  if (!this.form.goods.maintenance_doc) {
                    this.$set(this.form.goods, "maintenance_doc", {
                      isPdf: 1,
                      data: [],
                    });
                  } else {
                    this.form.goods.maintenance_doc.isPdf = 1;
                    this.form.goods.maintenance_doc.data = [];
                    this.currentPreviewImg = null;
                  }

                  for (let i = 0; i < images.length; i++) {
                    const { large_thumb, small_thumb } = images[i];

                    const { result, data } = await this.uploadSinglePage(
                      large_thumb,
                      i
                    );

                    if (result === 1) {
                      this.form.goods.maintenance_doc.data.push({
                        thumb_link: data.url,
                        thumb: data.attachment,
                        main_thumb: data.attachment,
                        high_url: data.high_relative_path,
                      });
                    }

                    this.uploadProgress =
                      Math.floor(((i + 1) / images.length) * 100) || 0;
                  }

                  if (this.form.goods.maintenance_doc.data.length) {
                    this.openImagePreviewUpload(
                      this.form.goods.maintenance_doc,
                      "maintenance_doc",
                      "保养手册",
                      "maintenance_doc"
                    );
                  } else {
                    this.$message.error("暂无PDF页图片上传成功");
                  }
                } finally {
                  this.visible = false;
                }

                break;
              }

              case "install_guide": {
                this.uploadUrl = this.uploadUrl.replace("=pdf", "=image");
                const images = await this.renderPdf(file.raw);
                this.$refs.upload.clearFiles();
                try {
                  this.uploadProgress = 0;
                  this.visible = true;

                  if (!this.form.goods.install_guide) {
                    this.$set(this.form.goods, "install_guide", {
                      isPdf: 1,
                      data: [],
                    });
                  } else {
                    this.form.goods.install_guide.isPdf = 1;
                    this.form.goods.install_guide.data = [];
                    this.currentPreviewImg = null;
                  }

                  for (let i = 0; i < images.length; i++) {
                    const { large_thumb, small_thumb } = images[i];

                    const { result, data } = await this.uploadSinglePage(
                      large_thumb,
                      i
                    );

                    if (result === 1) {
                      this.form.goods.install_guide.data.push({
                        thumb_link: data.url,
                        thumb: data.attachment,
                        main_thumb: data.attachment,
                        high_url: data.high_relative_path,
                      });
                    }

                    this.uploadProgress =
                      Math.floor(((i + 1) / images.length) * 100) || 0;
                  }

                  if (this.form.goods.maintenance_doc.data.length) {
                    this.openImagePreviewUpload(
                      this.form.goods.install_guide,
                      "install_guide",
                      "安装指南",
                      "install_guide"
                    );
                  } else {
                    this.$message.error("暂无PDF页图片上传成功");
                  }
                } finally {
                  this.visible = false;
                }

                break;
              }

              //规格组合明细-安装指南
              case "option_install_guide": {
                this.uploadUrl = this.uploadUrl.replace("=pdf", "=image");
                const images = await this.renderPdf(file.raw);
                this.$refs.upload.clearFiles();
                try {
                  this.uploadProgress = 0;
                  this.visible = true;

                  if (!option.install_guide) {
                    this.$set(option, "install_guide", {
                      isPdf: 1,
                      data: [],
                    });
                  } else {
                    option.install_guide.isPdf = 1;
                    option.install_guide.data = [];
                    this.currentPreviewImg = null;
                  }

                  for (let i = 0; i < images.length; i++) {
                    const { large_thumb, small_thumb } = images[i];

                    const { result, data } = await this.uploadSinglePage(
                      large_thumb,
                      i
                    );

                    if (result === 1) {
                      option.install_guide.data.push({
                        thumb_link: data.url,
                        thumb: data.attachment,
                        main_thumb: data.attachment,
                        high_url: data.high_relative_path,
                      });
                    }

                    this.uploadProgress =
                      Math.floor(((i + 1) / images.length) * 100) || 0;
                  }

                  if (option.install_guide.data.length) {
                    this.openImagePreviewUpload(
                      option.install_guide,
                      "option_install_guide",
                      "安装指南",
                      "option_install_guide"
                    );
                  } else {
                    this.$message.error("暂无PDF页图片上传成功");
                  }
                } finally {
                  this.visible = false;
                }

                break;
              }
            }
          }
        } else if (isCad || isMax) {
          if (fileList.every((x) => x.status == "ready")) {
            setTimeout(() => {
              this.uploadProgress = 0;
              this.visible = true;
              this.$refs.uploadFile.submit();
            }, 100);
          }

          if (
            !fileList.some((x) => x.status == "ready") &&
            !fileList.some((x) => x.status == "uploading")
          ) {
            this.$refs.uploadFile.clearFiles();
            this.visible = false;
          }
        } else {
          if (fileList.every((x) => x.status == "ready")) {
            this.uploadUrl = this.uploadUrl.replace("=pdf", "=image");
            setTimeout(() => {
              this.uploadProgress = 0;
              this.visible = true;
              this.$refs.upload.submit();
            }, 100);
          }

          if (
            !fileList.some((x) => x.status == "ready") &&
            !fileList.some((x) => x.status == "uploading")
          ) {
            this.$refs.upload.clearFiles();
            this.visible = false;
          }

          if (fileList.every((x) => x.status == "success")) {
            switch (this.formFieldName) {
              case "atlas": {
                this.openImagePreviewUpload(
                  this.form.goods.atlas,
                  "atlas",
                  "商品图册",
                  "atlas"
                );
                break;
              }
              case "maintenance_doc": {
                this.openImagePreviewUpload(
                  this.form.goods.maintenance_doc,
                  "maintenance_doc",
                  "保养手册",
                  "maintenance_doc"
                );
                break;
              }
              //规格组合明细-安装指南
              case "option_install_guide": {
                this.openImagePreviewUpload(
                  option.install_guide,
                  "option_install_guide",
                  "安装指南",
                  "option_install_guide"
                );
                break;
              }
              case "main": {
                this.openImagePreview(
                  this.form.goods.main_url,
                  "main",
                  "商品主图",
                  "thumb"
                );
                break;
              }
              case "other": {
                this.openImagePreview(
                  this.form.goods.thumb_url,
                  "other",
                  "商品效果图",
                  "thumb_url"
                );
                break;
              }
              case "real_image": {
                this.openImagePreview(
                  this.form.goods.real_image,
                  "real_image",
                  "商品实拍图",
                  "real_image"
                );
                break;
              }
              case "wiring_diagram": {
                this.openImagePreview(
                  this.form.goods.wiring_diagram,
                  "wiring_diagram",
                  "其它",
                  "wiring_diagram"
                );
                break;
              }
              //规格组合明细-走线示意图
              case "option_wiring_diagram": {
                this.openImagePreview(
                  option.wiring_diagram,
                  "option_wiring_diagram",
                  "走线图",
                  "option_wiring_diagram"
                );
                break;
              }
            }
          }
        }
      };

      this._debounce(fn)();
    },

    _debounce(fn, delay) {
      var delay = delay || 200;
      return function () {
        var th = this;
        var args = arguments;
        if (this.timer) {
          clearTimeout(timer);
        }
        this.timer = setTimeout(function () {
          this.timer = null;
          fn.apply(th, args);
        }, delay);
      };
    },

    async renderPdf(pdfFile) {
      const res = [];
      this.pdfProgress = 0;
      this.pdfVisible = true;

      try {
        const arrayBuffer = await pdfFile.arrayBuffer();
        pdfjsLib.GlobalWorkerOptions.workerSrc =
          "{{ resource_get('static/js/pdfjs/pdf.worker.min.js') }}";
        const loadingTask = pdfjsLib.getDocument(arrayBuffer);
        const pdf = await loadingTask.promise;
        console.log("=====", pdf);

        for (let i = 1; i <= pdf.numPages; i++) {
          const page = await pdf.getPage(i);
          const viewport = page.getViewport({
            scale: 2,
          });

          // 高清
          const largeCanvas = document.createElement("canvas");
          const largeCtx = largeCanvas.getContext("2d");
          largeCanvas.height = viewport.height;
          largeCanvas.width = viewport.width;
          const renderOptions = {
            canvasContext: largeCtx,
            viewport: viewport,
            textLayer: true,
          };
          await page.render(renderOptions).promise;
          const largeImage = largeCanvas.toDataURL("image/jpeg", 1);

          // 小图
          const smallCanvas = document.createElement("canvas");
          const smallCtx = smallCanvas.getContext("2d");
          let width = viewport.width;
          let height = viewport.height;
          const limit = 1024;
          if (width > limit) {
            const scale = limit / width;
            width = limit;
            height = Math.floor(height * scale);
          }
          smallCanvas.height = height;
          smallCanvas.width = width;
          smallCtx.drawImage(
            largeCanvas,
            0,
            0,
            largeCanvas.width,
            largeCanvas.height, // 源 Canvas 的矩形区域
            0,
            0,
            smallCanvas.width,
            smallCanvas.height // 目标 Canvas 的矩形区域
          );
          const imageSmall = smallCanvas.toDataURL("image/jpeg", 1);

          res.push({
            large_thumb: largeImage,
            small_thumb: imageSmall,
          });

          this.pdfProgress = ((i / pdf.numPages) * 100).toFixed(2) || 0;
        }
      } finally {
        this.pdfVisible = false;
      }

      return res;
    },

    base64ToBlob(base64Data, contentType = "") {
      const byteString = atob(base64Data.split(",")[1]);
      const arrayBuffer = new ArrayBuffer(byteString.length);
      const uint8Array = new Uint8Array(arrayBuffer);

      for (let i = 0; i < byteString.length; i++) {
        uint8Array[i] = byteString.charCodeAt(i);
      }

      return new Blob([arrayBuffer], { type: contentType });
    },

    blobToFile(blob, ext = "png") {
      return new File([blob], `document.${ext}`, {
        type: `application/${ext}`,
      });
    },

    async uploadSinglePage(imageData) {
      return new Promise((resolve, reject) => {
        try {
          const blob = this.base64ToBlob(imageData);
          const file = this.blobToFile(blob);
          const requestData = new FormData();
          requestData.append("file", file);
          requestData.append("thumb_type", this.uploadParams.thumb_type);
          requestData.append("is_high", this.uploadParams.is_high);

          this.$http
            .post(this.uploadUrl, requestData)
            .then(function (response) {
              resolve(response.data);
            });
        } catch (error) {
          this.$message.error(`上传PDF页失败: ${error.message}`);
        }
      });
    },

    filterObject(obj, keysToKeep) {
      return Object.keys(obj).reduce((acc, key) => {
        if (keysToKeep.includes(key)) {
          acc[key] = obj[key];
        } else {
          if (!this.isBase64Image(obj[key])) {
            acc[key] = obj[key];
          }
        }
        return acc;
      }, {});
    },

    isBase64Image(str) {
      if (typeof str !== "string") return false;

      // 判断是否以 data:image/ 开头，并包含 base64
      const regex = /^data:image\/(png|jpeg|jpg|gif|bmp|webp|svg\+xml);base64,/;

      if (!regex.test(str)) return false;

      // 去掉前缀，剩下的应该是Base64编码
      const base64Data = str.replace(regex, "");

      // 判断Base64编码是否有效（长度是否是4的倍数，且只包含Base64字符）
      // Base64字符集：A-Z a-z 0-9 + / =
      const base64Regex = /^[A-Za-z0-9+/=]+$/;

      if (!base64Regex.test(base64Data)) return false;

      // 长度必须是4的倍数
      if (base64Data.length % 4 !== 0) return false;

      return true;
    },

    onImagePreivewClose() {
      this.imagePreviewVisible = false;
      const option =
        this.specDetails[this.selectedSpecDetailsIndex]?.modelTypes[
          this.selectedSpecDetailTabsIndex
        ]?.option;

      switch (this.formFieldName) {
        case "main": {
          this.form.goods.main_url = this.previewImgList;
          break;
        }
        case "other": {
          this.form.goods.thumb_url = this.previewImgList;
          break;
        }
        case "real_image": {
          this.form.goods.real_image = this.previewImgList;
          break;
        }
        case "wiring_diagram": {
          this.form.goods.wiring_diagram = this.previewImgList;
          break;
        }
        case "atlas": {
          this.form.goods.atlas.data = this.previewImgList;
          break;
        }
        case "maintenance_doc": {
          this.form.goods.maintenance_doc.data = this.previewImgList;
          break;
        }
        //规格组合明细-安装指南
        case "option_install_guide": {
          option.install_guide.data = this.previewImgList;
          break;
        }
        //规格组合明细-走线示意图
        case "option_wiring_diagram": {
          option.wiring_diagram = this.previewImgList;
          break;
        }
      }
    },
  },
  //生命周期 - 销毁之前
  beforeDestroy() {
    // 组件销毁时，清理模型轮询定时器
    if (this.modelStatusTimer) {
      clearTimeout(this.modelStatusTimer);
      this.modelStatusTimer = null;
    }
    this.isPollingModelStatus = false;
  },
});

// Aman_log:goods.js
