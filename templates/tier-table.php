<?php
/**
 * Template: Buy More Save More tier table.
 *
 * Variables available:
 *   @var array<int, array<string, mixed>> $tiers      Tier rows with 'qty', 'discount', 'type', 'active'.
 *   @var int                              $product_id WC product ID.
 *   @var string                           $context    'product' or 'cart'.
 *
 * @package WooUpsellPro
 */

if (! defined('ABSPATH')) {
    exit;
}

if (empty($tiers)) {
    return;
}
?>
<div class="wup-tier-table wup-tier-table--<?php echo esc_attr($context ?? 'product'); ?>"
     data-product-id="<?php echo esc_attr((string) ($product_id ?? 0)); ?>"
     data-tiers="<?php echo esc_attr(wp_json_encode($tiers) ?: '[]'); ?>">

    <h4 class="wup-tier-table__heading">
        <?php esc_html_e('Buy more, save more!', 'woo-upsell-pro'); ?>
    </h4>

    <table class="wup-tier-table__table" role="table">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Quantity', 'woo-upsell-pro'); ?></th>
                <th scope="col"><?php esc_html_e('Discount', 'woo-upsell-pro'); ?></th>
                <th scope="col"><?php esc_html_e('Status', 'woo-upsell-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tiers as $tier) :
                $is_active  = ! empty($tier['active']);
                $row_class  = 'wup-tier-table__row';
                $row_class .= $is_active ? ' wup-tier-table__row--active' : '';
                $discount   = (int) ($tier['discount'] ?? 0);
                $min_qty    = (int) ($tier['qty'] ?? 1);
                $type       = $tier['type'] ?? 'percent';
            ?>
            <tr class="<?php echo esc_attr($row_class); ?>"
                data-min-qty="<?php echo esc_attr((string) $min_qty); ?>">
                <td><?php echo esc_html($min_qty . '+'); ?></td>
                <td>
                    <?php if ($type === 'percent') : ?>
                        <?php echo esc_html($discount . '% ' . __('OFF', 'woo-upsell-pro')); ?>
                    <?php else : ?>
                        <?php echo wp_kses_post(wc_price($discount)); ?> <?php esc_html_e('OFF', 'woo-upsell-pro'); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_active) : ?>
                        <span class="wup-tier-table__status wup-tier-table__status--active">
                            <?php esc_html_e('current', 'woo-upsell-pro'); ?>
                        </span>
                    <?php else : ?>
                        <span class="wup-tier-table__status wup-tier-table__status--locked">
                            <?php esc_html_e('locked', 'woo-upsell-pro'); ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
