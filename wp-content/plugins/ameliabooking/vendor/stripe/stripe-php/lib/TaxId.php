<?php

// File generated from our OpenAPI spec

namespace AmeliaStripe;

/**
 * You can add one or multiple tax IDs to a <a href="https://stripe.com/docs/api/customers">customer</a> or account.
 * Customer and account tax IDs get displayed on related invoices and credit notes.
 *
 * Related guides: <a href="https://stripe.com/docs/billing/taxes/tax-ids">Customer tax identification numbers</a>, <a href="https://stripe.com/docs/invoicing/connect#account-tax-ids">Account tax IDs</a>
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property null|string $country Two-letter ISO code representing the country of the tax ID.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property null|Customer|string $customer ID of the customer.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property null|(object{account?: Account|string, application?: Application|string, customer?: Customer|string, type: string}&StripeObject) $owner The account or customer the tax ID belongs to.
 * @property string $type Type of the tax ID, one of <code>ad_nrt</code>, <code>ae_trn</code>, <code>al_tin</code>, <code>am_tin</code>, <code>ao_tin</code>, <code>ar_cuit</code>, <code>au_abn</code>, <code>au_arn</code>, <code>aw_tin</code>, <code>az_tin</code>, <code>ba_tin</code>, <code>bb_tin</code>, <code>bd_bin</code>, <code>bf_ifu</code>, <code>bg_uic</code>, <code>bh_vat</code>, <code>bj_ifu</code>, <code>bo_tin</code>, <code>br_cnpj</code>, <code>br_cpf</code>, <code>bs_tin</code>, <code>by_tin</code>, <code>ca_bn</code>, <code>ca_gst_hst</code>, <code>ca_pst_bc</code>, <code>ca_pst_mb</code>, <code>ca_pst_sk</code>, <code>ca_qst</code>, <code>cd_nif</code>, <code>ch_uid</code>, <code>ch_vat</code>, <code>cl_tin</code>, <code>cm_niu</code>, <code>cn_tin</code>, <code>co_nit</code>, <code>cr_tin</code>, <code>cv_nif</code>, <code>de_stn</code>, <code>do_rcn</code>, <code>ec_ruc</code>, <code>eg_tin</code>, <code>es_cif</code>, <code>et_tin</code>, <code>eu_oss_vat</code>, <code>eu_vat</code>, <code>gb_vat</code>, <code>ge_vat</code>, <code>gn_nif</code>, <code>hk_br</code>, <code>hr_oib</code>, <code>hu_tin</code>, <code>id_npwp</code>, <code>il_vat</code>, <code>in_gst</code>, <code>is_vat</code>, <code>jp_cn</code>, <code>jp_rn</code>, <code>jp_trn</code>, <code>ke_pin</code>, <code>kg_tin</code>, <code>kh_tin</code>, <code>kr_brn</code>, <code>kz_bin</code>, <code>la_tin</code>, <code>li_uid</code>, <code>li_vat</code>, <code>ma_vat</code>, <code>md_vat</code>, <code>me_pib</code>, <code>mk_vat</code>, <code>mr_nif</code>, <code>mx_rfc</code>, <code>my_frp</code>, <code>my_itn</code>, <code>my_sst</code>, <code>ng_tin</code>, <code>no_vat</code>, <code>no_voec</code>, <code>np_pan</code>, <code>nz_gst</code>, <code>om_vat</code>, <code>pe_ruc</code>, <code>ph_tin</code>, <code>ro_tin</code>, <code>rs_pib</code>, <code>ru_inn</code>, <code>ru_kpp</code>, <code>sa_vat</code>, <code>sg_gst</code>, <code>sg_uen</code>, <code>si_tin</code>, <code>sn_ninea</code>, <code>sr_fin</code>, <code>sv_nit</code>, <code>th_vat</code>, <code>tj_tin</code>, <code>tr_tin</code>, <code>tw_vat</code>, <code>tz_vat</code>, <code>ua_vat</code>, <code>ug_tin</code>, <code>us_ein</code>, <code>uy_ruc</code>, <code>uz_tin</code>, <code>uz_vat</code>, <code>ve_rif</code>, <code>vn_tin</code>, <code>za_vat</code>, <code>zm_tin</code>, or <code>zw_tin</code>. Note that some legacy tax IDs have type <code>unknown</code>
 * @property string $value Value of the tax ID.
 * @property null|(object{status: string, verified_address: null|string, verified_name: null|string}&StripeObject) $verification Tax ID verification information.
 */
class TaxId extends ApiResource
{
    const OBJECT_NAME = 'tax_id';

    const TYPE_AD_NRT = 'ad_nrt';
    const TYPE_AE_TRN = 'ae_trn';
    const TYPE_AL_TIN = 'al_tin';
    const TYPE_AM_TIN = 'am_tin';
    const TYPE_AO_TIN = 'ao_tin';
    const TYPE_AR_CUIT = 'ar_cuit';
    const TYPE_AU_ABN = 'au_abn';
    const TYPE_AU_ARN = 'au_arn';
    const TYPE_AW_TIN = 'aw_tin';
    const TYPE_AZ_TIN = 'az_tin';
    const TYPE_BA_TIN = 'ba_tin';
    const TYPE_BB_TIN = 'bb_tin';
    const TYPE_BD_BIN = 'bd_bin';
    const TYPE_BF_IFU = 'bf_ifu';
    const TYPE_BG_UIC = 'bg_uic';
    const TYPE_BH_VAT = 'bh_vat';
    const TYPE_BJ_IFU = 'bj_ifu';
    const TYPE_BO_TIN = 'bo_tin';
    const TYPE_BR_CNPJ = 'br_cnpj';
    const TYPE_BR_CPF = 'br_cpf';
    const TYPE_BS_TIN = 'bs_tin';
    const TYPE_BY_TIN = 'by_tin';
    const TYPE_CA_BN = 'ca_bn';
    const TYPE_CA_GST_HST = 'ca_gst_hst';
    const TYPE_CA_PST_BC = 'ca_pst_bc';
    const TYPE_CA_PST_MB = 'ca_pst_mb';
    const TYPE_CA_PST_SK = 'ca_pst_sk';
    const TYPE_CA_QST = 'ca_qst';
    const TYPE_CD_NIF = 'cd_nif';
    const TYPE_CH_UID = 'ch_uid';
    const TYPE_CH_VAT = 'ch_vat';
    const TYPE_CL_TIN = 'cl_tin';
    const TYPE_CM_NIU = 'cm_niu';
    const TYPE_CN_TIN = 'cn_tin';
    const TYPE_CO_NIT = 'co_nit';
    const TYPE_CR_TIN = 'cr_tin';
    const TYPE_CV_NIF = 'cv_nif';
    const TYPE_DE_STN = 'de_stn';
    const TYPE_DO_RCN = 'do_rcn';
    const TYPE_EC_RUC = 'ec_ruc';
    const TYPE_EG_TIN = 'eg_tin';
    const TYPE_ES_CIF = 'es_cif';
    const TYPE_ET_TIN = 'et_tin';
    const TYPE_EU_OSS_VAT = 'eu_oss_vat';
    const TYPE_EU_VAT = 'eu_vat';
    const TYPE_GB_VAT = 'gb_vat';
    const TYPE_GE_VAT = 'ge_vat';
    const TYPE_GN_NIF = 'gn_nif';
    const TYPE_HK_BR = 'hk_br';
    const TYPE_HR_OIB = 'hr_oib';
    const TYPE_HU_TIN = 'hu_tin';
    const TYPE_ID_NPWP = 'id_npwp';
    const TYPE_IL_VAT = 'il_vat';
    const TYPE_IN_GST = 'in_gst';
    const TYPE_IS_VAT = 'is_vat';
    const TYPE_JP_CN = 'jp_cn';
    const TYPE_JP_RN = 'jp_rn';
    const TYPE_JP_TRN = 'jp_trn';
    const TYPE_KE_PIN = 'ke_pin';
    const TYPE_KG_TIN = 'kg_tin';
    const TYPE_KH_TIN = 'kh_tin';
    const TYPE_KR_BRN = 'kr_brn';
    const TYPE_KZ_BIN = 'kz_bin';
    const TYPE_LA_TIN = 'la_tin';
    const TYPE_LI_UID = 'li_uid';
    const TYPE_LI_VAT = 'li_vat';
    const TYPE_MA_VAT = 'ma_vat';
    const TYPE_MD_VAT = 'md_vat';
    const TYPE_ME_PIB = 'me_pib';
    const TYPE_MK_VAT = 'mk_vat';
    const TYPE_MR_NIF = 'mr_nif';
    const TYPE_MX_RFC = 'mx_rfc';
    const TYPE_MY_FRP = 'my_frp';
    const TYPE_MY_ITN = 'my_itn';
    const TYPE_MY_SST = 'my_sst';
    const TYPE_NG_TIN = 'ng_tin';
    const TYPE_NO_VAT = 'no_vat';
    const TYPE_NO_VOEC = 'no_voec';
    const TYPE_NP_PAN = 'np_pan';
    const TYPE_NZ_GST = 'nz_gst';
    const TYPE_OM_VAT = 'om_vat';
    const TYPE_PE_RUC = 'pe_ruc';
    const TYPE_PH_TIN = 'ph_tin';
    const TYPE_RO_TIN = 'ro_tin';
    const TYPE_RS_PIB = 'rs_pib';
    const TYPE_RU_INN = 'ru_inn';
    const TYPE_RU_KPP = 'ru_kpp';
    const TYPE_SA_VAT = 'sa_vat';
    const TYPE_SG_GST = 'sg_gst';
    const TYPE_SG_UEN = 'sg_uen';
    const TYPE_SI_TIN = 'si_tin';
    const TYPE_SN_NINEA = 'sn_ninea';
    const TYPE_SR_FIN = 'sr_fin';
    const TYPE_SV_NIT = 'sv_nit';
    const TYPE_TH_VAT = 'th_vat';
    const TYPE_TJ_TIN = 'tj_tin';
    const TYPE_TR_TIN = 'tr_tin';
    const TYPE_TW_VAT = 'tw_vat';
    const TYPE_TZ_VAT = 'tz_vat';
    const TYPE_UA_VAT = 'ua_vat';
    const TYPE_UG_TIN = 'ug_tin';
    const TYPE_UNKNOWN = 'unknown';
    const TYPE_US_EIN = 'us_ein';
    const TYPE_UY_RUC = 'uy_ruc';
    const TYPE_UZ_TIN = 'uz_tin';
    const TYPE_UZ_VAT = 'uz_vat';
    const TYPE_VE_RIF = 've_rif';
    const TYPE_VN_TIN = 'vn_tin';
    const TYPE_ZA_VAT = 'za_vat';
    const TYPE_ZM_TIN = 'zm_tin';
    const TYPE_ZW_TIN = 'zw_tin';

    /**
     * Creates a new account or customer <code>tax_id</code> object.
     *
     * @param null|array{expand?: string[], owner?: array{account?: string, customer?: string, type: string}, type: string, value: string} $params
     * @param null|array|string $options
     *
     * @return TaxId the created resource
     *
     * @throws Exception\ApiErrorException if the request fails
     */
    public static function create($params = null, $options = null)
    {
        self::_validateParams($params);
        $url = static::classUrl();

        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        $obj = Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }

    /**
     * Deletes an existing account or customer <code>tax_id</code> object.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @return TaxId the deleted resource
     *
     * @throws Exception\ApiErrorException if the request fails
     */
    public function delete($params = null, $opts = null)
    {
        self::_validateParams($params);

        $url = $this->instanceUrl();
        list($response, $opts) = $this->_request('delete', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * Returns a list of tax IDs.
     *
     * @param null|array{ending_before?: string, expand?: string[], limit?: int, owner?: array{account?: string, customer?: string, type: string}, starting_after?: string} $params
     * @param null|array|string $opts
     *
     * @return Collection<TaxId> of ApiResources
     *
     * @throws Exception\ApiErrorException if the request fails
     */
    public static function all($params = null, $opts = null)
    {
        $url = static::classUrl();

        return static::_requestPage($url, Collection::class, $params, $opts);
    }

    /**
     * Retrieves an account or customer <code>tax_id</code> object.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @return TaxId
     *
     * @throws Exception\ApiErrorException if the request fails
     */
    public static function retrieve($id, $opts = null)
    {
        $opts = Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }

    const VERIFICATION_STATUS_PENDING = 'pending';
    const VERIFICATION_STATUS_UNAVAILABLE = 'unavailable';
    const VERIFICATION_STATUS_UNVERIFIED = 'unverified';
    const VERIFICATION_STATUS_VERIFIED = 'verified';
}
