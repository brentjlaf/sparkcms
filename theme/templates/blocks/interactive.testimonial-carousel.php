<!-- File: interactive.testimonial-carousel.php -->
<!-- Template: interactive.testimonial-carousel -->
<templateSetting caption="Testimonial Carousel Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Autoplay (seconds)</dt>
        <dd>
            <input type="number" name="custom_autoplay" class="form-control" value="6" min="0" step="1">
            <p class="small text-muted mb-0">Set to 0 to disable automatic slide rotation.</p>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Show Pagination</dt>
        <dd>
            <label><input type="checkbox" name="custom_show_pagination" value=" true" checked> Display navigation dots</label>
        </dd>
    </dl>
    <hr>
    <dl class="sparkDialog _tpl-box">
        <dt>Quote 1</dt>
        <dd><textarea name="custom_quote1" class="form-control">SparkCMS helped us launch faster than we imagined, and our team loves how simple it is to update content.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Author 1</dt>
        <dd><input type="text" name="custom_author1" class="form-control" value="Alex Morgan"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Role 1</dt>
        <dd><input type="text" name="custom_role1" class="form-control" value="Director of Marketing, Horizon Labs"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Avatar 1</dt>
        <dd>
            <input type="text" name="custom_avatar1" id="custom_avatar1" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_avatar1')"><i class="fa-solid fa-image btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Rating 1</dt>
        <dd>
            <select name="custom_rating1" class="form-select">
                <option value="">No rating</option>
                <option value="5" selected>5 Stars</option>
                <option value="4">4 Stars</option>
                <option value="3">3 Stars</option>
                <option value="2">2 Stars</option>
                <option value="1">1 Star</option>
            </select>
        </dd>
    </dl>
    <hr>
    <dl class="sparkDialog _tpl-box">
        <dt>Quote 2</dt>
        <dd><textarea name="custom_quote2" class="form-control">The carousel block gives us a polished way to highlight client wins without needing a developer.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Author 2</dt>
        <dd><input type="text" name="custom_author2" class="form-control" value="Priya Patel"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Role 2</dt>
        <dd><input type="text" name="custom_role2" class="form-control" value="Operations Lead, Northwind Logistics"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Avatar 2</dt>
        <dd>
            <input type="text" name="custom_avatar2" id="custom_avatar2" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_avatar2')"><i class="fa-solid fa-image btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Rating 2</dt>
        <dd>
            <select name="custom_rating2" class="form-select">
                <option value="" selected>No rating</option>
                <option value="5">5 Stars</option>
                <option value="4">4 Stars</option>
                <option value="3">3 Stars</option>
                <option value="2">2 Stars</option>
                <option value="1">1 Star</option>
            </select>
        </dd>
    </dl>
    <hr>
    <dl class="sparkDialog _tpl-box">
        <dt>Quote 3</dt>
        <dd><textarea name="custom_quote3" class="form-control">Our support team finally has a single testimonial block they can drop anywhere on the site.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Author 3</dt>
        <dd><input type="text" name="custom_author3" class="form-control" value="Morgan Lee"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Role 3</dt>
        <dd><input type="text" name="custom_role3" class="form-control" value="VP of Customer Success, Brightline Studio"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Avatar 3</dt>
        <dd>
            <input type="text" name="custom_avatar3" id="custom_avatar3" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_avatar3')"><i class="fa-solid fa-image btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Rating 3</dt>
        <dd>
            <select name="custom_rating3" class="form-select">
                <option value="" selected>No rating</option>
                <option value="5">5 Stars</option>
                <option value="4">4 Stars</option>
                <option value="3">3 Stars</option>
                <option value="2">2 Stars</option>
                <option value="1">1 Star</option>
            </select>
        </dd>
    </dl>
</templateSetting>
<section class="testimonial-carousel" data-tpl-tooltip="Testimonial Carousel" data-testimonial-carousel data-autoplay="{custom_autoplay}" data-show-pagination="{custom_show_pagination}">
    <div class="container">
        <div class="swiper">
            <div class="swiper-wrapper">
                <article class="swiper-slide" data-default-quote="SparkCMS helped us launch faster than we imagined, and our team loves how simple it is to update content." data-default-author="Alex Morgan" data-default-role="Director of Marketing, Horizon Labs" data-default-rating="5">
                    <figure class="testimonial-card">
                        <blockquote class="testimonial-card__quote" data-quote>{custom_quote1}</blockquote>
                        <figcaption class="testimonial-card__meta">
                            <div class="testimonial-card__profile">
                                <div class="testimonial-card__avatar" data-avatar>
                                    <img src="{custom_avatar1}" alt="{custom_author1}" loading="lazy">
                                </div>
                                <div class="testimonial-card__author">
                                    <div class="testimonial-card__name" data-author>{custom_author1}</div>
                                    <div class="testimonial-card__role" data-role>{custom_role1}</div>
                                </div>
                            </div>
                            <div class="testimonial-card__rating" data-rating="{custom_rating1}"></div>
                        </figcaption>
                    </figure>
                </article>
                <article class="swiper-slide" data-default-quote="The carousel block gives us a polished way to highlight client wins without needing a developer." data-default-author="Priya Patel" data-default-role="Operations Lead, Northwind Logistics" data-default-rating="4">
                    <figure class="testimonial-card">
                        <blockquote class="testimonial-card__quote" data-quote>{custom_quote2}</blockquote>
                        <figcaption class="testimonial-card__meta">
                            <div class="testimonial-card__profile">
                                <div class="testimonial-card__avatar" data-avatar>
                                    <img src="{custom_avatar2}" alt="{custom_author2}" loading="lazy">
                                </div>
                                <div class="testimonial-card__author">
                                    <div class="testimonial-card__name" data-author>{custom_author2}</div>
                                    <div class="testimonial-card__role" data-role>{custom_role2}</div>
                                </div>
                            </div>
                            <div class="testimonial-card__rating" data-rating="{custom_rating2}"></div>
                        </figcaption>
                    </figure>
                </article>
                <article class="swiper-slide" data-default-quote="Our support team finally has a single testimonial block they can drop anywhere on the site." data-default-author="Morgan Lee" data-default-role="VP of Customer Success, Brightline Studio" data-default-rating="5">
                    <figure class="testimonial-card">
                        <blockquote class="testimonial-card__quote" data-quote>{custom_quote3}</blockquote>
                        <figcaption class="testimonial-card__meta">
                            <div class="testimonial-card__profile">
                                <div class="testimonial-card__avatar" data-avatar>
                                    <img src="{custom_avatar3}" alt="{custom_author3}" loading="lazy">
                                </div>
                                <div class="testimonial-card__author">
                                    <div class="testimonial-card__name" data-author>{custom_author3}</div>
                                    <div class="testimonial-card__role" data-role>{custom_role3}</div>
                                </div>
                            </div>
                            <div class="testimonial-card__rating" data-rating="{custom_rating3}"></div>
                        </figcaption>
                    </figure>
                </article>
            </div>
        </div>
        <div class="testimonial-carousel__controls">
            <button class="testimonial-carousel__nav testimonial-carousel__nav--prev" type="button" data-carousel-prev aria-label="Previous testimonial">
                <span aria-hidden="true">&larr;</span>
            </button>
            <div class="testimonial-carousel__pagination" data-carousel-pagination></div>
            <button class="testimonial-carousel__nav testimonial-carousel__nav--next" type="button" data-carousel-next aria-label="Next testimonial">
                <span aria-hidden="true">&rarr;</span>
            </button>
        </div>
        <div class="testimonial-carousel__empty" data-carousel-empty>No testimonials available yet. Add content to see it here.</div>
    </div>
</section>
